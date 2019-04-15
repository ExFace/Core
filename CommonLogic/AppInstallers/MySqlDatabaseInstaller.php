<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * Database AppInstaller for Apps with MySQL Database.
 *
 * @author Ralf Mulansky
 *
 */

class MySqlDatabaseInstaller extends AbstractSqlDatabaseInstaller
{    
    private $migration_table_query = 'CREATE TABLE IF NOT EXISTS `_migrations` (
                                     `id` int(8) NOT NULL AUTO_INCREMENT,
                                     `migration_name` varchar(300) NOT NULL,
                                     `up_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                     `up_script` varchar(1000) NOT NULL,
                                     `up_result` varchar(1000) NOT NULL,
                                     `down_datetime` timestamp NULL,
                                     `down_script` varchar(1000) NOT NULL,
                                     `down_result` varchar(100) NULL,
                                      PRIMARY KEY (`id`)
                                      ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;';  
    
    /**
     *
     * @return string
     */
    protected function getSqlDbType()
    {
        return 'MySQL';
    }
    
    /**
     *
     * @return string
     */
    protected function getCommentSign()
    {
        return '#';
    }
    
    /**
     * Checks if _migrations table already exist, if not creates the table
     * 
     * @param string SqlDataConnectorInterface $connection
     * @return string
     */  
    protected function ensureMigrationsTableExists(SqlDataConnectorInterface $connection)
    {
        $sql = 'SHOW tables LIKE "_migrations"';
        $result = ' Migration Table already exists';
        if (empty($connection->runSql($sql)->getResultArray())) {
            try {
                $this->getDataConnection()->transactionStart();
                foreach (preg_split("/;\R/", $this->migration_table_query) as $statement) {
                    if ($statement) {
                        $this->getDataConnection()->runSql($statement);
                    }
                }
                $this->getDataConnection()->transactionCommit();
                $result = 'Migration Table generated ';
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
                $connection->transactionRollback();
                throw new InstallerRuntimeError($this, 'Generating Migration table failed!');
            }
        }
        return $result;
    }
    
    /**
     * Gets all migrations from DB that are currently UP/applied
     *
     * @param string SqlDataConnectorInterface $connection
     * @return SqlMigration[]
     */
    protected function getMigrationsFromDb($connection)
    {
        $this->ensureMigrationsTableExists($connection);
        //DESC, damit Down Skripte von neuster zu ältester Version ausgeführt werden
        $sql = 'SELECT * FROM _migrations WHERE down_datetime IS NULL ORDER BY migration_name DESC';
        //$array = array ();
        $array = $connection->runSql($sql)->getResultArray();
        $migrs = array ();
        
        //$array = $query->getResultArray();
        if (empty($array)){
            return $migrs;
        }
        foreach ($array as $a){
            $mig = new SqlMigration($a['migration_name'], $a['up_script'], $a['down_script']);
            $mig->setId($a['id']);
            $mig->setUpDatetime($a['up_datetime']);
            $mig->setUpResult($a['up_result']);
            $migrs[] = $mig;      
        }
        return $migrs;
        
    }
    
    /**
     * UPs/applies the Migration $migration and writes Log into _migrations table
     *
     * @param SqlMigration $migration
     *        SqlDataConnectorInterface $connection
     * @return SqlMigration[]
     */
    protected function migrateUp(SqlMigration $migration, SqlDataConnectorInterface $connection)
    {
        if ($migration->isUp()) {
            throw new InstallerRuntimeError($this, 'Migration ' . $migration->getMigrationName() . ' already up!');
        }
        $this->ensureMigrationsTableExists($connection);
        $up_script = $migration->getUpScript();
        try {
            $connection->transactionStart();
            foreach (preg_split("/;\R/", $up_script) as $statement) {
                if ($statement) {
                    $up_result_array = $connection->runSql($statement)->getResultArray();                    
                    //Erscheint unschön. Gibt mgl. bessere Variante
                    $up_result .= '| '. implode('| ', $up_result_array);
                }                
            }
            $migration_name = $migration->getMigrationName();
            $down_script = $migration->getDownScript();
            $sql_insert = "INSERT INTO _migrations (migration_name, up_script, up_result, down_script)
                        VALUES ('$migration_name', \"'$up_script'\", '$up_result', \"'$down_script'\");";
            $query_insert = $connection->runSql($sql_insert);
            $id = intval($query_insert->getLastInsertId());
            $connection->transactionCommit();            
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $connection->transactionRollback();
            throw new InstallerRuntimeError($this, 'Migration up' . $migration->getMigrationName() . ' failed!');
        }
        $sql_select = "SELECT * FROM _migrations WHERE id='$id'";
        $select_array = $connection->runSql($sql_select)->getResultArray();
        if (empty($select_array)){
            throw new InstallerRuntimeError($this, 'Migration up ' . $migration->getMigrationName() . ' failed to write into _migrations table!');
        }
        $migration->setId($id);
        $migration->setUpResult($up_result);
        //Array kann eigentlich nur eine Resultzeile als Array als Inhalt haben, da id PRIMARY KEY
        $migration->setUpDatetime($select_array[0]['up_datetime']);
        return $migration;        
    }
    
    /**
     * DOWNs/Reverts the Migration $migration and writes Log into _migrations table
     *
     * @param SqlMigration $migration
     *        SqlDataConnectorInterface $connection
     * @return SqlMigration[]
     */
    protected function migrateDown(SqlMigration $migration, SqlDataConnectorInterface $connection)
    {
        if ($migration->isDown()) {
            throw new InstallerRuntimeError($this, 'Migration ' . $migration->getMigrationName() . ' already down!');
        }
        $this->ensureMigrationsTableExists($connection);
        $down_script=$migration->getDownScript();
        $id = $migration->getId();
        try {
            $connection->transactionStart();
            foreach (preg_split("/;\R/", $down_script) as $statement) {
                if ($statement) {
                    $down_result_array=$connection->runSql($statement)->getResultArray();
                    //Erscheint unschön. Gibt mgl. bessere Variante
                    $down_result .= '| '. implode('| ', $down_result_array);
                } 
            }
            $sql_update = "UPDATE _migrations SET down_datetime=now(), down_result='$down_result' WHERE id='$id';";
            $connection->runSql($sql_update);
            $connection->transactionCommit();
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            $connection->transactionRollback();
            throw new InstallerRuntimeError($this, 'Migration down ' . $migration->getMigrationName() . ' failed!');
        }
        $sql_select = "SELECT * FROM _migrations WHERE id='$id'";
        $select_array = $connection->runSql($sql_select)->getResultArray();
        if (empty($select_array)){
            throw new InstallerRuntimeError($this, 'Something went very wrong');
        }
        $migration->setDownResult($down_result);        
        //Array kann eigentlich nur eine Resultzeile als Array als Inhalt haben, da id PRIMARY KEY
        $migration->setDownDatetime($select_array[0]['down_datetime']);
        return $migration;
    }
}
?>