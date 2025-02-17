<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use function GuzzleHttp\json_encode;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataConnectors\MySqlConnector;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\UnexpectedValueException;

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
    private $migrationTableCheckedFlag = false;

    private string $delimiter = ";";

    /**
     *
     * @return string
     */
    protected function getSqlDbType() : string
    {
        return 'MySQL';
    }

    protected function getDelimiter() : string
    {
        return $this->delimiter;
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
                    $connection->runSql($database_create)->freeResult();
                    $database_use = "USE {$dbName};";
                    $connection->runSql($database_use)->freeResult();
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
        if ($this->migrationTableCheckedFlag === true) {
            return;
        }
        try {
            $migrations_table_create = $this->buildSqlMigrationTableCreate();
            $this->runSqlMultiStatementScript($connection, $migrations_table_create);
            $this->getWorkbench()->getLogger()->debug('SQL migration table' . $this->getMigrationsTableName() . ' created! ');
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            throw new InstallerRuntimeError($this, 'Generating Migration table failed! ' . $e->getMessage(), null, $e);
        }
        $this->migrationTableCheckedFlag = true;
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
        $query = $connection->runSql($sql);
        $migrs_db = $query->getResultArray();
        $query->freeResult();
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
            $upScript = $migration->getUpScript();
            if ($migration->isFromDb() === false || $migration->isFailed()) {
                $upScript = $this->addFunctions($upScript);
            }

            $up_result = $this->runSqlMultiStatementScript($connection, $upScript, false);
            $up_result_string = $this->stringifyQueryResults($up_result);
            foreach ($up_result as $query) {
                $query->freeResult();
            }
            $time = new \DateTime();
            $sqlMigrationInsert = $this->buildSqlMigrationUpInsert($migration, $up_result_string, $time);
            $connection->runSql($sqlMigrationInsert)->freeResult();
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
     * @throws \Throwable
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::migrateDown()
     */
    protected function migrateDown(SqlMigration $migration, SqlDataConnectorInterface $connection): SqlMigration
    {
        $this->ensureMigrationsTableExists($connection);
        $time = new \DateTime();
        $downScript = $migration->getDownScript();
        if (empty($downScript)) {
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ' has no down script');
            $migration->setDown(DateTimeDataType::formatDateNormalized($time), '');
        }
        try {
            $connection->transactionStart();
            if ($migration->isFromDb() === false) {
                $downScript = $this->addFunctions($downScript);
            }

            $down_result = $this->runSqlMultiStatementScript($connection, $downScript);
            $down_result_string = $this->stringifyQueryResults($down_result);
            foreach ($down_result as $query) {
                $query->freeResult();
            }
            $sql_script = $this->buildSqlMigrationDownUpdate($migration, $down_result_string, $time);
            $connection->runSql($sql_script)->freeResult();
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
            $migration->setFailed(true);
            if ($exception->getPrevious()) {
                $migration->setFailedMessage($exception->getPrevious()->getMessage());
            } else {
                $migration->setFailedMessage($exception->getMessage());            
            }
            if ($exception instanceof ExceptionInterface) {
                $migration->setFailedLogId($exception->getId());
            }
            if ($up) {
                $sql_script = $this->buildSqlMigrationUpFailed($migration, new \DateTime());
                $functions = $this->findFunctions($migration->getUpScript());
            } else {
                $sql_script = $this->buildSqlMigrationDownFailed($migration, new \DateTime());
                $functions = $this->findFunctions($migration->getDownScript());
            }

            $remove_function_script = $this->getRemoveFunctionScript($functions);
            $connection->transactionStart();
            if (empty($remove_function_script) === false) {
                try {
                    $connection->runSql($remove_function_script, true)->freeResult();
                } catch (\Throwable $eCleanup) {
                    $eCleanup = new InstallerRuntimeError($this, 'Error in SQL cleanup after migration failure. ' . $eCleanup->getMessage(), null, $eCleanup);
                    $this->getWorkbench()->getLogger()->logException($eCleanup);
                    // Do not throw the exception here - cleanup errors are not critical!
                }
            }

            $connection->runSql($sql_script)->freeResult();
            $connection->transactionCommit();
            
        } catch (\Throwable $e) {
            try {
                $connection->transactionRollback();
            } catch (\Throwable $eRollback) {
                // Commands out of sync will prevent any further interaction with the DB, so we just reset
                // the connection here, which should also rollback automatically.
                if (stripos($eRollback->getMessage(), 'Commands out of sync') !== false) {
                    $this->getDataConnection()->disconnect();
                    $this->getDataConnection()->connect();
                }
                $this->getWorkbench()->getLogger()->logException($eRollback);
            }
            $this->getWorkbench()->getLogger()
                ->logException($exception)
                ->logException($e);
            throw new InstallerRuntimeError($this, 'Migration ' . $migration->getMigrationName() . ' failure log error: ' . $e->getMessage(), null, $e);
        }
        return $migration;
    }
    
    protected function addFunctions(string $script) : string
    {
        $prefix = '';
        $postfix = '';
        
        foreach ($this->findFunctions($script) as $funcName) {
            $prefix .= $this->getFunctionCreateScript($funcName) ?? '';
            $postfix .= $this->getFunctionDropScript($funcName);
        }
        
        return $prefix . $script . $postfix;
    }

    /**
     * Finds all external functions and stored procedures within the given SQL and returns their names in an array.
     * 
     * External means the stored procedure is used in in the SQL, but is not created in it.
     *
     * E.g. the following provided SQL will produce the array below
     * 
     * ```
     * CALL add_column_if_missing('exf_pwa_dataset', 'incremental_flag', 'tinyint NOT NULL')
     * CALL remove_column_if_exists('exf_pwa_dataset', 'incremental_flag');
     * 
     * ```
     * 
     * --> `[add_column_if_missing, remove_column_if_exists]`
     *
     * @param string $script
     * @return array
     */
    protected function findFunctions(string $script) : array
    {
        $callPattern = '/CALL (?<functionNames>\w+)\(/i';
        $callMatches = [];
        preg_match_all($callPattern, $script, $callMatches);
        $calledNames = array_unique($callMatches['functionNames'] ?? []);
        
        $createPattern = '/CREATE PROCEDURE (?<functionNames>\w+)\(/i';
        $createMatches = [];
        preg_match_all($createPattern, $script, $createMatches);
        $createdNames = array_unique($createMatches['functionNames'] ?? []);
        
        return array_diff($calledNames, $createdNames);
    }
    
    /**
     * Returns the SQL to create a utility stored procedure or NULL if no such utility definition is found
     * 
     * @param string $funcName
     * @throws NotImplementedError
     * @throws UnexpectedValueException
     * @return string|NULL
     */
    protected function getFunctionCreateScript(string $funcName) : ?string
    {
        $folder = $this->getWorkbench()->getCoreApp()->getDirectoryAbsolutePath()
        . DIRECTORY_SEPARATOR . 'QueryBuilders'
        . DIRECTORY_SEPARATOR . 'SqlFunctions'
        . DIRECTORY_SEPARATOR . $this->getSqlDbType()
        . DIRECTORY_SEPARATOR;
        
        $filepath = $folder . $funcName . '.sql';
        
        if (false === file_exists($filepath)) {
            return null;
        }
        
        $sql = file_get_contents($filepath);
        if ($sql === '' || $sql === false) {
            throw new NotImplementedError('Requested SQL function \'' . $funcName
                . '\' has not been implemented');
        }

        if (strrpos($sql, $this->delimiter) !== strlen($sql)-1) {
            throw new UnexpectedValueException('Used SQL function \'' .$funcName
                . '\' does not end with an delimiter! Please edit file.');
        }
        
        return $sql;
    }

    /**
     * @param array $functions
     * @return string
     */
    protected function getRemoveFunctionScript(array $functions): string
    {
        $remove_function_script = '';
        foreach ($functions as $functionName) {
            $remove_function_script .= $this->getFunctionDropScript($functionName);
        }
        return $remove_function_script;
    }
    
    /**
     * 
     * @param string $funcName
     * @return string
     */
    protected function getFunctionDropScript(string $funcName) : string
    {
        return "DROP PROCEDURE IF EXISTS {$funcName}" . $this->delimiter;
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
     * Returns SQL statement to create migrations table.
     * 
     * @return string
     */
    protected function buildSqlMigrationTableCreate() : string
    {
        // in case any changes need to be made to the migrations table, make the changes in the CREATE TABLE statement
        // also add the changes as a seperate statement (like the ones below the CREATE TABLE statement) so that
        // already existing installations will be updated
        return <<<SQL

-- BATCH-DELIMITER ----------------

-- creation of migrations table       
CREATE TABLE IF NOT EXISTS `{$this->getMigrationsTableName()}` (
    `id` int(8) NOT NULL AUTO_INCREMENT,
    `migration_name` varchar(300) NOT NULL,
    `up_datetime` timestamp NOT NULL,
    `up_script` longtext NOT NULL,
    `up_result` longtext NULL,
    `down_datetime` timestamp NULL,
    `down_script` longtext NOT NULL,
    `down_result` longtext NULL,
    `failed_flag` tinyint(1) NOT NULL DEFAULT 0,
    `failed_message` longtext NULL,
    `skip_flag` tinyint(1) NOT NULL DEFAULT 0,
    `log_id` varchar(10) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

----------------
-- update to add `failed_flag`, `failed_message` and `skip_flag` columns
SELECT count(*)
INTO @exist
FROM information_schema.columns
WHERE table_schema = DATABASE()
and COLUMN_NAME LIKE '%failed%'
AND table_name = '{$this->getMigrationsTableName()}' LIMIT 1;

set @query = IF(@exist <= 0, 'ALTER TABLE `{$this->getMigrationsTableName()}` ADD COLUMN (
        `failed_flag` tinyint(1) NOT NULL DEFAULT 0,
        `failed_message` longtext NULL,
        `skip_flag` tinyint(1) NOT NULL DEFAULT 0
    )',
'select \'Column Exists\' status');

prepare stmt from @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

----------------
-- update to add `log_id` column
SELECT count(*)
INTO @exist
FROM information_schema.columns
WHERE table_schema = DATABASE()
and COLUMN_NAME LIKE '%log_id%'
AND table_name = '{$this->getMigrationsTableName()}' LIMIT 1;

set @query = IF(@exist <= 0, 'ALTER TABLE `{$this->getMigrationsTableName()}` ADD COLUMN (
        `log_id` varchar(10) NULL
    )',
'select \'Column Exists\' status');

prepare stmt from @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
        $upScript = $this->escapeSqlStringValue(StringDataType::encodeUTF8($this->addFunctions($migration->getUpScript())));
        $downScript = $this->escapeSqlStringValue(StringDataType::encodeUTF8($this->addFunctions($migration->getDownScript())));

        if ($id = $migration->getId()) {
            return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    up_datetime={$this->escapeSqlDateTimeValue($time)},
    up_script='{$upScript}',
    up_result='{$this->escapeSqlStringValue($up_result_string)}',
    down_datetime=NULL,
    down_script='{$downScript}',
    down_result=NULL,
    log_id=NULL,
    failed_flag=0,
    failed_message=NULL
WHERE id='{$id}';

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
        '{$upScript}',
        '{$this->escapeSqlStringValue($up_result_string)}',
        '{$downScript}'
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
        $upScript = $this->escapeSqlStringValue(StringDataType::encodeUTF8($this->addFunctions($migration->getUpScript())));
        $downScript = $this->escapeSqlStringValue(StringDataType::encodeUTF8($this->addFunctions($migration->getDownScript())));

        if ($id = $migration->getId()) {
            if ($migration->getFailedLogId() !== null) {
                $logIdEntry = "log_id='{$migration->getFailedLogId()}',";
            }            
            return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    up_datetime={$this->escapeSqlDateTimeValue($time)},
    up_script='{$upScript}',
    up_result=NULL,
    down_datetime=NULL,
    down_script='{$downScript}',
    down_result=NULL,
    {$logIdEntry}
    failed_flag=1,
    failed_message='{$this->escapeSqlStringValue($migration->getFailedMessage())}'
WHERE id='{$id}';

SQL;
        }
        $logIdColumn = '';
        $logId = '';
        if ($migration->getFailedLogId() !== null) {
            $logIdColumn = "log_id,";
            $logId = "'{$migration->getFailedLogId()}',";
        }
        return <<<SQL
        
INSERT INTO {$this->getMigrationsTableName()}
    (
        migration_name,
        up_datetime,
        up_script,
        down_script,
        {$logIdColumn}
        failed_flag,
        failed_message
    )
    VALUES (
        '{$this->escapeSqlStringValue($migration->getMigrationName())}',
        {$this->escapeSqlDateTimeValue($time)},
        '{$upScript}',
        '{$downScript}',
        {$logId}
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
        $downScript = $this->escapeSqlStringValue(StringDataType::encodeUTF8($this->addFunctions($migration->getDownScript())));
        return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    down_datetime={$this->escapeSqlDateTimeValue($time)},
    down_script='{$downScript}',
    down_result='{$this->escapeSqlStringValue($down_result_string)}',
    log_id=NULL,
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
        $downScript = $this->escapeSqlStringValue(StringDataType::encodeUTF8($this->addFunctions($migration->getDownScript())));

        $logIdEntry = '';
        if ($migration->getFailedLogId() !== null) {
            $logIdEntry = "log_id='{$migration->getFailedLogId()}',";
        }       
        return <<<SQL
        
UPDATE {$this->getMigrationsTableName()}
SET
    down_datetime={$this->escapeSqlDateTimeValue($time)},
    down_script='{$downScript}',
    down_result=NULL,
    {$logIdEntry}
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
            throw new InstallerRuntimeError($this, 'Cannot use connection "'
                . $connection->getAliasWithNamespace()
                . '" with MySQL DB installer: only instances of "MySqlConnector" supported!');
        }
        return $connection;
    }
}