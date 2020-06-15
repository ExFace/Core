<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use function GuzzleHttp\json_encode;
use exface\Core\DataTypes\StringDataType;

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
     * @return self
     */ 
    protected function ensureMigrationsTableExists(SqlDataConnectorInterface $connection) : SqlDataConnectorInterface
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
        }        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::getMigrationsFromDb()
     */
    protected function getMigrationsFromDb(SqlDataConnectorInterface $connection) : array
    {
        $this->ensureMigrationsTableExists($connection);
        //DESC, damit Down Skripte von neuster zu ältester Version ausgeführt werden
        $sql = "SELECT * FROM {$this->getMigrationsTableName()} WHERE down_datetime IS NULL ORDER BY migration_name DESC";
        $migrs_db = $connection->runSql($sql)->getResultArray();
        $migrs = array ();       
        if (empty($migrs_db)){
            return $migrs;
        }
        foreach ($migrs_db as $a){
            $mig = new SqlMigration($a['migration_name'], $a['up_script'], $a['down_script']);
            $mig->setUp(intval($a['id']), $a['up_datetime'], $a['up_result']);
            $migrs[] = $mig;      
        }
        return $migrs;        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::migrateUp()
     */
    protected function migrateUp(SqlMigration $migration, SqlDataConnectorInterface $connection) : SqlMigration
    {
        if ($migration->isUp() == TRUE) {
            throw new InstallerRuntimeError($this, 'Migration ' . $migration->getMigrationName() . ' already up!');
        }
        $this->ensureMigrationsTableExists($connection);
        $up_script = $migration->getUpScript();
        try {
            $connection->transactionStart();
            $up_result = $this->runSqlMultiStatementScript($connection, $up_script, false);
            $up_result_string = $this->stringifyQueryResults($up_result);            
            $migration_name = $migration->getMigrationName();
            $down_script = $migration->getDownScript();
            $sql_insert = $this->buildSqlMigrationTableInsert($migration_name, $up_script, $up_result_string, $down_script);
            $query_insert = $connection->runSql($sql_insert);
            $id = intval($query_insert->getLastInsertId());
            $connection->transactionCommit();
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration_name . ': script UP executed successfully.');            
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $connection->transactionRollback();
            throw new InstallerRuntimeError($this, 'Migration up ' . $migration->getMigrationName() . ' failed!', null, $e);
        }
        $sql_select = "SELECT * FROM {$this->getMigrationsTableName()} WHERE id='$id'";
        $select_array = $connection->runSql($sql_select)->getResultArray();
        if (empty($select_array)){
            throw new InstallerRuntimeError($this, 'Migration up ' . $migration->getMigrationName() . ' failed to write into migrations table!');
        }       
        $migration->setUp($id, $select_array[0]['up_datetime'], $up_result_string);
        return $migration;        
    }  
           
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::migrateDown()
     */
    protected function migrateDown(SqlMigration $migration, SqlDataConnectorInterface $connection) : SqlMigration
    {
        if ($migration->isUp() == FALSE) {
            throw new InstallerRuntimeError($this, 'Migration ' . $migration->getMigrationName() . ' already down!');
        }
        $this->ensureMigrationsTableExists($connection);
        $down_script=$migration->getDownScript();
        if (empty($down_script)){
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ': Migration has no down script');
            return $migration;
        }
        $id = $migration->getId();
        try {
            $connection->transactionStart();
            $down_result = $this->runSqlMultiStatementScript($connection, $down_script, false);
            $down_result_string = $this->stringifyQueryResults($down_result);
            //da Transaction Rollback nicht korrekt funktioniert
            $sql_update = <<<SQL
            
UPDATE {$this->getMigrationsTableName()}
SET down_datetime={$this->getSqlFunctionForCurrentDateTime()}, down_result='{$this->escapeSqlStringValue($down_result_string)}'
WHERE id='{$id}';

SQL;
            $connection->runSql($sql_update);
            $connection->transactionCommit();
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration->getMigrationName() . ': script DOWN executed successfully ');
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $connection->transactionRollback();
            throw new InstallerRuntimeError($this, 'Migration down ' . $migration->getMigrationName() . ' failed!');
        }
        $sql_select = "SELECT * FROM {$this->getMigrationsTableName()} WHERE id='$id'";
        $select_array = $connection->runSql($sql_select)->getResultArray();
        if (empty($select_array)){
            throw new InstallerRuntimeError($this, 'Something went very wrong');
        }
        $migration->setDown($select_array[0]['down_datetime'], $down_result_string);
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
        return <<<SQL
        
CREATE TABLE IF NOT EXISTS `{$this->getMigrationsTableName()}` (
`id` int(8) NOT NULL AUTO_INCREMENT,
`migration_name` varchar(300) NOT NULL,
`up_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
`up_script` longtext NOT NULL,
`up_result` longtext NOT NULL,
`down_datetime` timestamp NULL,
`down_script` longtext NOT NULL,
`down_result` longtext NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

SQL;
    }
    
    /**
     * 
     * @param string $migration_name
     * @param string $up_script
     * @param string $up_result_string
     * @param string $down_script
     * @return string
     */
    protected function buildSqlMigrationTableInsert(string $migration_name, string $up_script, string $up_result_string, string $down_script) : string
    {
        return <<<SQL
    
INSERT INTO {$this->getMigrationsTableName()}
    (
        migration_name,
        up_script,
        up_result,
        down_script
    )
    VALUES (
        '{$this->escapeSqlStringValue($migration_name)}',
        '{$this->escapeSqlStringValue(StringDataType::encodeUTF8($up_script))}',
        '{$this->escapeSqlStringValue($up_result_string)}',
        '{$this->escapeSqlStringValue(StringDataType::encodeUTF8($down_script))}'
    );
    
SQL;
    }
    
    /**
     * Returns the correct Sql function to get the current date and time.
     * 
     * @return string
     */
    protected function getSqlFunctionForCurrentDateTime() : string
    {
        return 'now()';
    }
}
?>