<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use function GuzzleHttp\json_encode;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataConnectors\MySqlConnector;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * Database AppInstaller for Apps with MySQL Database.
 * 
 * ## Encoding
 * 
 * This installer currently requires SQL files to be encoded as UTF8!!!
 * 
 * ## Transaction handling
 * 
 * NOTE: MySQL does not support rollbacks of DDL-statements. This is why the
 * MySqlDatabaseInstaller wraps each UP/DOWN script in a transaction - this
 * ensures, that if a script was performed successfully, all it's changes
 * are committed - DDL and DML. If not done so, DML changes might get rolled
 * back if something in the next migration script goes wrong, while DDL
 * changes would remain due to their implicit commit.
 *
 * @author Ralf Mulansky
 *
 */
class MySqlDatabaseInstaller extends AbstractSqlDatabaseInstaller
{   
    /**
     *
     * @return string
     */
    protected function getSqlDbType() : string
    {
        return 'MySQL';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::installDatabase()
     */
    protected function installDatabase(SqlDataConnectorInterface $connection, string $indent = '') : \Iterator
    {        
        $msg = '';
        try {
            $connection->connect();
        } catch (DataConnectionFailedError $e) {
            $mysqlException = $e->getPrevious();
            if ($mysqlException instanceof \mysqli_sql_exception) {
                if ($mysqlException->getCode() === 1049) {
                    $dbName = $connection->getDbase();
                    $connection->setDbase('');
                    $connection->connect();
                    $database_create = "CREATE DATABASE {$dbName} CHARACTER SET utf8 COLLATE utf8_general_ci";
                    $connection->runSql($database_create);
                    $database_use = "USE {$dbName};";
                    $connection->runSql($database_use);
                    $connection->disconnect();
                    $connection->setDbase($dbName);
                    $msg = 'Database ' . $dbName . ' created! ';
                }
            } else {
                throw $e;
            }
        }
        yield $indent . $msg . PHP_EOL;
    }
    
    /**
     * 
     * @param SqlDataConnectorInterface $connection
     * @throws InstallerRuntimeError
     */
    protected function ensureMigrationsTableExists(SqlDataConnectorInterface $connection) : void
    {
        $sql = $this->buildSqlMigrationTableShow();
        if (empty($connection->runSql($sql)->getResultArray())) {
            try {
                $migrations_table_create = $this->buildSqlMigrationTableCreate();
                $this->runSqlMultiStatementScript($connection, $migrations_table_create);
                $this->getWorkbench()->getLogger()->debug('SQL migration table' . $this->getMigrationsTableName() . ' created! ');
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
                throw new InstallerRuntimeError($this, 'Generating Migration table failed!');
            }
            return;
        }
        $sql = $this->buildSqlShowColumnFailed();
        if (empty($connection->runSql($sql)->getResultArray())) {
            try {
                $columns_create = $this->buildSqlMigrationTableAtler();
                $this->runSqlMultiStatementScript($connection, $columns_create);
                $this->getWorkbench()->getLogger()->debug('Added columns \'failed\', \'failed_message\', \'skip flag\' to existing migration table ' . $this->getMigrationsTableName() . '.');
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
                throw new InstallerRuntimeError($this, 'Adding columns \'failed\', \'failed_message\', \'skip flag\' to existing migration table ' . $this->getMigrationsTableName() . ' failed.');
            }
            return;
        }
        return;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::getMigrationsFromDb()
     */
    protected function getMigrationsFromDb(SqlDataConnectorInterface $connection): array
    {
        $this->ensureMigrationsTableExists($connection);        
        $sql = $this->buildSqlSelectMigrationsFromDb();
        $migrs_db = $connection->runSql($sql)->getResultArray();
        $migrs = array();
        if (empty($migrs_db)) {
            return $migrs;
        }
        foreach ($migrs_db as $a) {
            $mig = SqlMigration::constructFromDb($a);
            if ($migrs[$mig->getMigrationName()] === null) {
                $migrs[$mig->getMigrationName()] = $mig;
            }
        }
        return $migrs;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::migrateUp()
     */
    protected function migrateUp(SqlMigration $migration, SqlDataConnectorInterface $connection): SqlMigration
    {
        $this->ensureMigrationsTableExists($connection);
        try {
            $connection->transactionStart();
            $up_result = $this->runSqlMultiStatementScript($connection, $migration->getUpScript(), false);
            $up_result_string = $this->stringifyQueryResults($up_result);
            $time = new \DateTime();
            $sqlMigrationInsert = $this->buildSqlMigrationUpInsert($migration, $up_result_string, $time);
            $connection->runSql($sqlMigrationInsert);
            $connection->transactionCommit();
            $migration->setUp(DateTimeDataType::formatDateNormalized($time), $up_result_string);
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ': script UP executed successfully ');
        } catch (\Throwable $e) {
            $connection->transactionRollback();
            throw $e;
        }
        
        return $migration;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::migrateDown()
     */
    protected function migrateDown(SqlMigration $migration, SqlDataConnectorInterface $connection): SqlMigration
    {
        $this->ensureMigrationsTableExists($connection);
        if (empty($migration->getDownScript())) {
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ' has no down script');
            return false;
        }
        try {
            $connection->transactionStart();
            $down_result = $this->runSqlMultiStatementScript($connection, $migration->getDownScript(), false);
            $down_result_string = $this->stringifyQueryResults($down_result);
            $time = DateTimeDataType::now();
            $sql_script = $this->buildSqlMigrationDownUpdate($migration, $down_result_string, $time);
            $connection->runSql($sql_script);
            $connection->transactionCommit();
            $migration->setDown(DateTimeDataType::formatDateNormalized($time), $down_result_string);
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ' DOWN script executed successfully ');
        } catch (\Throwable $e) {
            $connection->transactionRollback();
            throw $e;
        }
        
        return $migration;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::migrateFail()
     */
    protected function migrateFail(SqlMigration $migration, SqlDataConnectorInterface $connection, bool $up, \Throwable $exception) : SqlMigration
    {
        try {
            $migration->setFailed(true)->setFailedMessage($exception->getMessage());
            if ($up) {
                $sql_script = $this->buildSqlMigrationUpFailed($migration, new \DateTime());
            } else {
                $sql_script = $this->buildSqlMigrationDownFailed($migration, new \DateTime());
            }
            
            $connection->transactionStart();
            $connection->runSql($sql_script);
            $connection->transactionCommit();
            
        } catch (\Throwable $e) {
            $connection->transactionRollback();
            $this->getWorkbench()->getLogger()->logException($e);
            throw new InstallerRuntimeError($this, 'Migration ' . $migration->getMigrationName() . ' failure log error: ' . $e->getMessage(), null, $e);
        }
        return $migration;
    }

    /**
     *
     * @param string $value
     * @return string
     */
    protected function escapeSqlStringValue(string $value) : string
    {
        return addslashes($value);
    }
    
    /**
     * 
     * @param \DateTime $time
     * @return string
     */
    protected function escapeSqlDateTimeValue(\DateTime $time) : string
    {
        return "'" . DateTimeDataType::formatDateNormalized($time) . "'";
    }
    
    /**
     * Returns SQL statement to check if migration table exists.
     * 
     * @return string
     */
    protected function buildSqlMigrationTableShow() : string
    {
        return "SHOW tables LIKE '{$this->getMigrationsTableName()}'";
    }
    
    /**
     * Returns SQL statement to create migrations table.
     * 
     * @return string
     */
    protected function buildSqlMigrationTableCreate() : string
    {
        // Add columns 'failed', 'failed_message', 'skip flag' to existing migration table if they don't exist.
        // down_datetime   failed_flag
        // NULL            0           -> UP-script successful, migration present
        // NULL            1           -> UP-script failure, migration not present
        // NOT NULL        0           -> DOWN-script successful, migration not present
        // NOT NULL        1           -> DOWN-script failure, migration present
        return <<<SQL
        
CREATE TABLE IF NOT EXISTS `{$this->getMigrationsTableName()}` (
    `id` int(8) NOT NULL AUTO_INCREMENT,
    `migration_name` varchar(300) NOT NULL,
    `up_datetime` timestamp NOT NULL,
    `up_script` longtext NOT NULL,
    `up_result` longtext,
    `down_datetime` timestamp NULL,
    `down_script` longtext NOT NULL,
    `down_result` longtext NULL,
    `failed_flag` tinyint(1) NOT NULL DEFAULT 0,
    `failed_message` longtext NULL,
    `skip_flag` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

SQL;
    }
    
   /**
    * SQL statement to check if `failed` column exist in migration table
    * 
    * @return string
    */
    protected function buildSqlShowColumnFailed() : string
    {
        return <<<SQL
        
SHOW COLUMNS FROM {$this->getMigrationsTableName()} LIKE '%failed%';

SQL;
    }
    
    /**
     * SQL statement to add columns `failed`, `failed_message` and `skip_flag` to migrations table.
     * 
     * @return string
     */
    protected function buildSqlMigrationTableAtler() : string
    {
        return <<<SQL
        
ALTER TABLE {$this->getMigrationsTableName()} ADD COLUMN (
    `failed_flag` tinyint(1) NOT NULL DEFAULT 0,
    `failed_message` longtext NULL,
    `skip_flag` tinyint(1) NOT NULL DEFAULT 0
);
ALTER TABLE {$this->getMigrationsTableName()} MODIFY `up_result` longtext;

SQL;
    }
    
    /**
     * Sql statement to insert/update a migration in the migration table.
     * 
     * @param string $migration_name
     * @param string $up_script
     * @param string $up_result_string
     * @param string $down_script
     * @return string
     */
    protected function buildSqlMigrationUpInsert(SqlMigration $migration, string $up_result_string, \DateTime $time) : string
    {
        if ($migration->getId()) {
         return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    up_datetime={$this->escapeSqlDateTimeValue($time)},
    up_script='{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getUpScript()))}',
    up_result='{$this->escapeSqlStringValue($up_result_string)}',
    down_datetime=NULL,
    down_script='{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getDownScript()))}',
    down_result=NULL,
    failed_flag=0,
    failed_message=NULL
WHERE id='{$migration->getId()}';

SQL;
        }
        
        return <<<SQL
        
INSERT INTO {$this->getMigrationsTableName()}
    (
        migration_name,
        up_datetime,
        up_script,
        up_result,
        down_script
    )
    VALUES (
        '{$this->escapeSqlStringValue($migration->getMigrationName())}',
        {$this->escapeSqlDateTimeValue($time)},
        '{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getUpScript()))}',
        '{$this->escapeSqlStringValue($up_result_string)}',
        '{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getDownScript()))}'
    );
    
SQL;
    }
    
    /**
     * Sql statement to insert/update a failed migration in the migration table
     * 
     * @param SqlMigration $migration
     * @param \DateTime $time
     * @return string
     */
    protected function buildSqlMigrationUpFailed(SqlMigration $migration, \DateTime $time) :string
    {
        if ($migration->getId()) {
        return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    up_datetime={$this->escapeSqlDateTimeValue($time)},
    up_script='{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getUpScript()))}',
    up_result=NULL,
    down_datetime=NULL,
    down_script='{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getDownScript()))}',
    down_result=NULL,
    failed_flag=1,
    failed_message='{$this->escapeSqlStringValue($migration->getFailedMessage())}'
WHERE id='{$migration->getId()}';

SQL;
        }
        
        return <<<SQL
        
INSERT INTO {$this->getMigrationsTableName()}
    (
        migration_name,
        up_datetime,
        up_script,
        down_script,
        failed_flag,
        failed_message
    )
    VALUES (
        '{$this->escapeSqlStringValue($migration->getMigrationName())}',
        {$this->escapeSqlDateTimeValue($time)},
        '{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getUpScript()))}',
        '{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getDownScript()))}',
        1,
        '{$this->escapeSqlStringValue($migration->getFailedMessage())}'
    );
    
SQL;
    }
    
    /**
     * Sql statement to update a migration entry after down script got executed.
     * 
     * @param SqlMigration $migration
     * @param string $down_result_string
     * @param \DateTime $time
     * @return string
     */
    protected function buildSqlMigrationDownUpdate(SqlMigration $migration, string $down_result_string, \DateTime $time) :string
    {
        return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    down_datetime={$this->escapeSqlDateTimeValue($time)},
    down_script='{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getDownScript()))}',
    down_result='{$this->escapeSqlStringValue($down_result_string)}',
    failed_flag=0,
    failed_message=NULL
WHERE id='{$migration->getId()}';

SQL;
    }
    
    /**
     * 
     * @param SqlMigration $migration
     * @param \DateTime $time
     * @return string
     */
    protected function buildSqlMigrationDownFailed(SqlMigration $migration, \DateTime $time) : string
    {
        return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    down_datetime={$this->escapeSqlDateTimeValue($time)},
    down_script='{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getDownScript()))}',
    down_result=NULL,
    failed_flag=1,
    failed_message='{$this->escapeSqlStringValue($migration->getFailedMessage())}'
WHERE id='{$migration->getId()}';

SQL;
    }
    
    /**
     * Sql statement to read migrations present in migrations table.
     * 
     * @return string
     */
    protected function buildSqlSelectMigrationsFromDb() : string
    {
        //DESC name and up_datetime, so we have the right order for down migration operations and the newest entry for a migration first
        return "SELECT * FROM {$this->getMigrationsTableName()} ORDER BY migration_name DESC, up_datetime DESC";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::checkDataConnection()
     */
    protected function checkDataConnection(SqlDataConnectorInterface $connection) : SqlDataConnectorInterface
    {
        if (! $connection instanceof MySqlConnector) {
            throw new InstallerRuntimeError($this, 'Cannot use connection "' . $connection->getAliasWithNamespace() . '" with MySQL DB installer: only instances of "MySqlConnector" supported!');
        }
        return $connection;
    }
}