<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use function GuzzleHttp\json_encode;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataConnectors\MySqlConnector;

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
    protected function installDatabase(SqlDataConnectorInterface $connection, string $indent = '') : string
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
        return $indent . $msg;
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
            $mig = new SqlMigration($a['migration_name'], $a['up_script'], $a['down_script']);
            $mig->initFromDb($a);
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
            $sqlMigrationInsert = $this->buildSqlMigrationUpInsert($migration, $up_result_string);
            $connection->runSql($sqlMigrationInsert);
            $connection->transactionCommit();
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ': script UP executed successfully ');
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $connection->transactionRollback();
            $migration->setFailed(true)->setFailedMessage($e->getMessage());
            $sql_script = $this->buildSqlMigrationUpFailed($migration);
            $this->migrationFailed($migration, $connection, $sql_script);
            throw new InstallerRuntimeError($this, 'Migration up ' . $migration->getMigrationName() . ' failed! See Log filed for more information.', null, $e);
        }
        
        //not sure if still needed and if so needs refactor
        /*$sql_select = "SELECT * FROM {$this->getMigrationsTableName()} WHERE id='{$migration->getId()}'";
        $select_array = $connection->runSql($sql_select)->getResultArray();
        if (empty($select_array)) {
            $this->migrationFailed($migration, $connection, 'Migration up ' . $migration->getMigrationName() . ' failed to write into migrations table!');
            throw new InstallerRuntimeError($this, 'Migration up ' . $migration->getMigrationName() . ' failed to write into migrations table!');
        }
        $migration->setUp($select_array[0]['up_datetime'], $up_result_string);*/
        
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
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ': Migration has no down script');
            return false;
        }
        try {
            $connection->transactionStart();
            $down_result = $this->runSqlMultiStatementScript($connection, $migration->getDownScript(), false);
            $down_result_string = $this->stringifyQueryResults($down_result);
            //da Transaction Rollback nicht korrekt funktioniert
            $sql_script = $this->buildSqlMigrationDownUpdate($migration, $down_result_string);
            $connection->runSql($sql_script);
            $connection->transactionCommit();
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ': script DOWN executed successfully ');
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $connection->transactionRollback();
            $migration->setFailed(true)->setFailedMessage($e->getMessage());
            $sql_script = $this->buildSqlMigrationDownFailed($migration);
            $this->migrationFailed($migration, $connection, $sql_script);
            throw new InstallerRuntimeError($this, 'Migration down ' . $migration->getMigrationName() . ' failed! See Log files for more information.');
        }
        
        //not sure if still needed, if so, needs rework
        /*$sql_select = "SELECT * FROM {$this->getMigrationsTableName()} WHERE id='{$migration->getId()}'";
        $select_array = $connection->runSql($sql_select)->getResultArray();
        if (empty($select_array)) {
            $this->migrationFailed($migration, $connection, 'Something went very wrong');
            throw new InstallerRuntimeError($this, 'Something went very wrong');
        }
        $migration->setDown($select_array[0]['down_datetime'], $down_result_string);*/
        
        return $migration;
    }

    /**
     * Runs the passed SQL-script to write error logs to the DB.
     *
     * @param SqlMigration $migration
     * @param SqlDataConnectorInterface $connection
     * @param string $sql_script
     * @return SqlMigration
     */
    protected function migrationFailed(SqlMigration $migration, SqlDataConnectorInterface $connection, string $sql_script): SqlMigration
    {
        try {
            $connection->transactionStart();
            $connection->runSql($sql_script);
            $connection->transactionCommit();
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ' failed. Log entry added. See migration logs for this app for further information and to fix the SQL script.');
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $connection->transactionRollback();
            throw new InstallerRuntimeError($this, 'Migration ' . $migration->getMigrationName() . ' failed. No log entry could be added.', null, $e);
        }
        return $migration;
    }

    /**
     *
     * @param string $value
     * @return string
     */
    protected function escapeSqlStringValue(string $value): string
    {
        return addslashes($value);
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
    `up_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `up_script` longtext NOT NULL,
    `up_result` longtext,
    `down_datetime` timestamp NULL,
    `down_script` longtext NOT NULL,
    `down_result` longtext NULL,
    `failed_flag` tinyint(1) NOT NULL DEFAULT 0,
    `failed_message` longtext NULL,
    `skip_flag` tinyint(1) NOT NULL DEFAULT 0
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
    protected function buildSqlMigrationUpInsert(SqlMigration $migration, string $up_result_string) : string
    {
        if ($migration->getId()) {
         return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    up_datetime={$this->buildSqlFunctionNow()},
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
        up_script,
        up_result,
        down_script
    )
    VALUES (
        '{$this->escapeSqlStringValue($migration->getMigrationName())}',
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
     * @return string
     */
    protected function buildSqlMigrationUpFailed(SqlMigration $migration) :string
    {
        if ($migration->getId()) {
        return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    up_datetime={$this->buildSqlFunctionNow()},
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
        up_script,
        down_script,
        failed_flag,
        failed_message
    )
    VALUES (
        '{$this->escapeSqlStringValue($migration->getMigrationName())}',
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
     * @return string
     */
    protected function buildSqlMigrationDownUpdate(SqlMigration $migration, string $down_result_string) :string
    {
        return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    down_datetime={$this->buildSqlFunctionNow()},
    down_script='{$this->escapeSqlStringValue(StringDataType::encodeUTF8($migration->getDownScript()))}',
    down_result='{$this->escapeSqlStringValue($down_result_string)}',
    failed_flag=0,
    failed_message=NULL
WHERE id='{$migration->getId()}';

SQL;
    }
    
    protected function buildSqlMigrationDownFailed(SqlMigration $migration) : string
    {
        return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    down_datetime={$this->buildSqlFunctionNow()},
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
     * Returns the SQL function call to get the current date and time.
     * 
     * @return string
     */
    protected function buildSqlFunctionNow() : string
    {
        return 'now()';
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