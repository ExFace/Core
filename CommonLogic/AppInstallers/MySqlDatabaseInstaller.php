<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * Database AppInstaller for Apps with MySQL Database.
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
     * Checks if migrations table already exist, if not creates the table
     * 
     * @param SqlDataConnectorInterface $connection
     * @return MySqlDatabaseInstaller
     */  
    protected function ensureMigrationsTableExists(SqlDataConnectorInterface $connection) : MySqlDatabaseInstaller
    {
        $sql = <<<SQL

SHOW tables LIKE "{$this->getMigrationsTableName()}";

SQL;
        if (empty($connection->runSql($sql)->getResultArray())) {
            try {
                $migrations_table_create = <<<SQL

CREATE TABLE IF NOT EXISTS `{$this->getMigrationsTableName()}` (
`id` int(8) NOT NULL AUTO_INCREMENT,
`migration_name` varchar(300) NOT NULL,
`up_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
`up_script` text NOT NULL,
`up_result` varchar(1000) NOT NULL,
`down_datetime` timestamp NULL,
`down_script` text NOT NULL,
`down_result` varchar(1000) NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

SQL;
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
     * Gets all migrations from database that are currently UP/applied
     *
     * @param SqlDataConnectorInterface $connection
     * @return SqlMigration[]
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
            $mig->setId($a['id']);
            $mig->setUpDatetime($a['up_datetime']);
            $mig->setUpResult($a['up_result']);
            $mig->setIsUp(TRUE);
            $migrs[] = $mig;      
        }
        return $migrs;        
    }
    
    /**
     * UPs/applies the Migration $migration and writes Log into migrations table
     *
     * @param SqlMigration $migration
     * @param SqlDataConnectorInterface $connection
     * @return SqlMigration[]
     */
    protected function migrateUp(SqlMigration $migration, SqlDataConnectorInterface $connection) : SqlMigration
    {
        if ($migration->getIsUp() == TRUE) {
            throw new InstallerRuntimeError($this, 'Migration ' . $migration->getMigrationName() . ' already up!');
        }
        $this->ensureMigrationsTableExists($connection);
        $up_script = $migration->getUpScript();
        try {
            $connection->transactionStart();
            $up_result = json_encode($this->runSqlMultiStatementScript($connection, $up_script, false));
            //da Transaction Rollback nicht korrekt funktioniert
            $migration->setIsUp(TRUE);
            $migration_name = $migration->getMigrationName();
            $down_script = $migration->getDownScript();
            $sql_insert = <<<SQL

INSERT INTO {$this->getMigrationsTableName()} 
    (
        migration_name, 
        up_script, 
        up_result, 
        down_script
    )
    VALUES (
        "{$this->escapeSqlStringValue($migration_name)}", 
        "{$this->escapeSqlStringValue($up_script)}", 
        "{$this->escapeSqlStringValue($up_result)}", 
        "{$this->escapeSqlStringValue($down_script)}"
    );

SQL;
            $query_insert = $connection->runSql($sql_insert);
            $id = $query_insert->getLastInsertId();
            $migration->setId($id);
            $connection->transactionCommit();
            $this->getWorkbench()->getLogger()->debug('SQL ' . $migration_name . ': script UP executed successfully ');            
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $connection->transactionRollback();
            throw new InstallerRuntimeError($this, 'Migration up ' . $migration->getMigrationName() . ' failed!');
        }
        $sql_select = "SELECT * FROM {$this->getMigrationsTableName()} WHERE id='$id'";
        $select_array = $connection->runSql($sql_select)->getResultArray();
        if (empty($select_array)){
            throw new InstallerRuntimeError($this, 'Migration up ' . $migration->getMigrationName() . ' failed to write into migrations table!');
        }        
        $migration->setUpResult($up_result);
        //Array kann eigentlich nur eine Resultzeile als Array als Inhalt haben, da id PRIMARY KEY
        $migration->setUpDatetime($select_array[0]['up_datetime']);
        return $migration;        
    }  
   
    /**
     * DOWNs/Reverts the Migration $migration and writes Log into migrations table
     *
     * @param SqlMigration $migration
     * @param SqlDataConnectorInterface $connection
     * @return SqlMigration[]
     */
    protected function migrateDown(SqlMigration $migration, SqlDataConnectorInterface $connection) : SqlMigration
    {
        if ($migration->getIsUp() == FALSE) {
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
            $down_result = json_encode($this->runSqlMultiStatementScript($connection, $down_script, false));
            //da Transaction Rollback nicht korrekt funktioniert
            $migration->setIsUp(FALSE);
            $sql_update = <<<SQL
            
UPDATE {$this->getMigrationsTableName()}
SET down_datetime=now(), down_result="{$this->escapeSqlStringValue($down_result)}"
WHERE id='$id';

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
        $migration->setDownResult($down_result);        
        //Array kann eigentlich nur eine Resultzeile als Array als Inhalt haben, da id PRIMARY KEY
        $migration->setDownDatetime($select_array[0]['down_datetime']);
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
}
?>