<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;

/**
 * This creates and manages SQL databases and performs SQL updates.
 *
 * If the app has it's own SQL database (= is not built on top of an existing
 * data source), changes to the SQL schema must go hand-in-hand with changes of
 * the meta model and the code. This installer takes care of migrating the schema
 * by performing SQL scripts stored in a specifal folder within the app (by
 * default "install/sql").
 *
 * How to add an SqlDatabaseInstaller to your app:
 * 1) Make sure, your app includes the following folder structure: Install/Sql/%Database_Version%/
 * 2) Place your init scripts in Install/%DatabaseVersion%/Sql/InitDB and your migration scripts in
 * Install/%Database_Version%/Sql/Migrations. The scripts will be executed in alphabetic order.
 * 3) Write specific Installer for your Database Version (for example MySQL) extending this Abstract Class
 * and add that Installer to the getInstaller() method of your app as follows:
 *
 
 public function getInstaller(InstallerInterface $injected_installer = null)
 {
 $installer = parent::getInstaller($injected_installer);
 
 // ...receding installers here...
 
 $schema_installer = new SqlDatabaseInstaller($this->getSelectorInstalling());
 $schema_installer->setDataConnection(...);
 $installer->addInstaller($schema_installer);
 
 // ...subsequent installers here...
 
 return $installer;
 }
 
 *
 * @author Ralf Mulansky
 *
 */
abstract class AbstractSqlDatabaseInstaller extends AbstractAppInstaller
{
    private $sql_db_type = '';
    
    private $comment_sign = '';
    
    private $sql_folder_name = 'Sql';
    
    private $sql_migrations_folder_name = 'Migrations';
    
    private $sql_install_folder_name = 'Install';
    
    private $sql_initdb_folder_name = 'InitDB';
    
    private $sql_demodata_folder_name = 'DemoData';
    
    private $sql_uninstall_folder_name = 'uninstall';
    
    private $data_connection = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install($source_absolute_path)
    {
        //TODO Test ob Db-Schema existiert
        $result = $this->performSql($source_absolute_path, $this->getSqlInitDbFolderName());
        $result .= $this->update($source_absolute_path);
        $result .= $this->performSql($source_absolute_path, $this->getSqlDemoDataFolderName());
        return $result;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::update()
     */
    public function update($source_absolute_path)
    {
        $migrationsInApp = $this->getMigrationsFromApp($source_absolute_path);
        $migrationsInDB = $this->getMigrationsFromDb($this->getDataConnection());
        
        foreach ($this->getMigrations($migrationsInDB, $migrationsInApp) as $migration) {
            $this->migrateDown($migration, $this->getDataConnection());
        }
        foreach ($this->getMigrations($migrationsInApp, $migrationsInDB) as $migration) {
            $this->migrateUp($migration, $this->getDataConnection());
        }
        return ' Migration successful!';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall()
    {
        return 'Automatic uninstaller not implemented for' . $this->getSelectorInstalling()->getAliasWithNamespace() . '!';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup($destination_absolute_path)
    {
        return 'SQL Backup not implemented for installer "' . $this->getSelectorInstalling()->getAliasWithNamespace() . '"!';
    }
    
    /**
     *
     * @return string
     */
    public function getSqlFolderName()
    {
        return $this->sql_folder_name;
    }
    
    /**
     *
     * @param string $value
     * @return $this
     */
    public function setSqlFolderName($value)
    {
        $this->sql_folder_name = $value;
        return $this;
    }
    
    /**
     * Default: %app_folder%/Install/sql
     *
     * @return string
     */
    public function getSqlFolderAbsolutePath($source_absolute_path)
    {
        return $this->getInstallFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . $this->getSqlFolderName();
    }
    
    /**
     *
     * @return string
     */
    public function getSqlInstallFolderName()
    {
        return $this->sql_install_folder_name;
    }
    
    /**
     *
     * @param string $value
     * @return $this
     */
    public function setSqlInstallFolderName($value)
    {
        $this->sql_install_folder_name = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getSqlInitDbFolderName()
    {
        return $this->sql_initdb_folder_name;
    }
    
    /**
     *
     * @param string $value
     * @return $this
     */
    public function setSqlInitDbFolderName($value)
    {
        $this->sql_initdb_folder_name = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getSqlDemoDataFolderName()
    {
        return $this->sql_demodata_folder_name;
    }
    
    /**
     *
     * @param string $value
     * @return $this
     */
    public function setSqlDemoDataFolderName($value)
    {
        $this->sql_demodata_folder_name = $value;
        return $this;
    }
    /**
     *
     * @return string
     */
    public function getSqlMigrationsFolderName()
    {
        return $this->sql_migrations_folder_name;
    }
    
    /**
     *
     * @param string $value
     * @return $this
     */
    public function setSqlMigrationsFolderName($value)
    {
        $this->sql_migrations_folder_name = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getSqlUninstallFolderName()
    {
        return $this->sql_uninstall_folder_name;
    }
    
    /**
     *
     * @param string $value
     * @return $this
     */
    public function setSqlUninstallFolderName($value)
    {
        $this->sql_uninstall_folder_name = $value;
        return $this;
    }
    
    /**
     *
     * @return SqlDataConnectorInterface
     */
    public function getDataConnection()
    {
        return $this->data_connection;
    }
    
    /**
     *
     * @param SqlDataConnectorInterface $value
     * @return $this
     */
    public function setDataConnection(SqlDataConnectorInterface $value)
    {
        $this->data_connection = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    abstract protected function getSqlDbType() :string ;
    
    /**
     *
     * @return string
     */
    abstract protected function getCommentSign() :string ;
    
    /**
     * 
     * @param SqlMigration $migration
     * @param SqlDataConnectorInterface $connection
     * @return SqlMigration
     */
    abstract protected function migrateDown(SqlMigration $migration, SqlDataConnectorInterface $connection) : SqlMigration;
    
    /**
     * 
     * @param SqlMigration $migration
     * @param SqlDataConnectorInterface $connection
     * @return SqlMigration
     */
    abstract protected function migrateUp(SqlMigration $migration, SqlDataConnectorInterface $connection) : SqlMigration;
               

    /**
     * Iterates through the files in "%source_absolute_path%/%install_folder_name%/%sql_folder_name%/%sql_db_type%/%folder_name%"
     * and all subfolders attempting to execute the SQL scripts stored in those files
     *
     * @param string $source_absolute_path
     *        string $folder_name
     * @return string
     */    
    protected function performSql($source_absolute_path, string $folder_name)
    {
        $files = $this->getFiles($source_absolute_path, $folder_name);
        $result = ' No SQL Files found!';
        foreach ($files as $file){
            $this->getWorkbench()->getLogger()->debug('Performing SQL');
            $sql = file_get_contents($file);
            $sql = $this->stripComments($sql);
            $connection = $this->getDataConnection();
            try {
                $connection->transactionStart();
                foreach (preg_split("/;\R/", $sql) as $statement) {
                    if ($statement) {
                        $connection->runSql($statement);
                    }
                }
                $connection->transactionCommit();
                $result = $folder_name . ' ' . $file . ' Performing Sql succeded ';
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
                $connection->transactionRollback();
                $result = $folder_name . ' ' . $file . ' Performing Sql failed ';
            }
        }
        return $result;
    }
       
    /**
     * Gets all files in the folder %source_absolute_path%/%install_folder_name%/%sql_folder_name%/%sql_db_type%/%folder_name% and all subfolders
     * 
     * @param string $source_absolute_path
     *        string $folder_name
     * @return array
     */
    protected function getFiles($source_absolute_path, string $folder_name)
    {
        $folder_path = $this->getSqlFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . $this->getSqlDbType() . DIRECTORY_SEPARATOR . $folder_name;
        $files = array();
        if (is_dir($folder_path) === false) {
            return $files;
        }              
        $files=$this->getFilesFromDir($folder_path);
        return $files;
    }
    
    
    /**
     *
     * @param SqlDataConnectorInterface $connection
     * @return SqlMigration[]
     */
    abstract protected function getMigrationsFromDb($connection) : array;
    
    /**
     * 
     * @param string $source_absolute_path
     * @return SqlMigration[]
     */
    protected function getMigrationsFromApp($source_absolute_path)
    {
        $migrs = [];
        foreach ($this->getFiles($source_absolute_path, $this->getSqlMigrationsFolderName()) as $path) {
            $fileContents = file_get_contents($path);
            $migrs[] = new SqlMigration(pathinfo($path, PATHINFO_FILENAME), $this->getMigrationScript($fileContents), $this->getMigrationScript($fileContents, false));
        }
        return $migrs;
    }
    
    /**
     * Builds an array containing items that are in $migrations_base but are not in $migrations_substract
     * 
     * @param array $migrations_base
     *        array $migrations_substract
     * @return SqlMigration[]
     */
    protected function getMigrations(array $migrations_base, array $migrations_substract)
    {        
        if (empty($migrations_substract)){
            return $migrations_base;
        }
        $arr = array ();
        foreach ($migrations_base as $mB) {
            $check = false;
            foreach ($migrations_substract as $mS) {
                if ($mB->equals($mS)) {
                    $check = true;
                }
            }
            if ($check === false){
                $arr[] = $mB;
            }
        }
        return $arr;
    }
    
    /**
     * Cuts the input string at the $comment_sign.DOWN occurence
     * and gives back either the part before that or from that point on
     * 
     * @param string $src
     *        bool $up
     * @return string
     */
    protected function getMigrationScript(string $src, bool $up = true)
    {
        $length=strlen($src);
        $cut=strpos($src, $this->getCommentSign() . 'DOWN');
        if ($cut == FALSE){
            throw new InstallerRuntimeError($this, ' Migrationscript has no Down Script ');
        }
       
        if ($up === true){
            $migstr = substr($src, 0, $cut);
        } elseif ($up === false){
            $migstr = substr($src, $cut, ($length-$cut));
        }
        
        return $this->stripComments($migstr);
    }
    
    /**
     * Gets all files in the $folder_path folder and all subfolders
     * 
     * @param string $folder
     * @return array
     */
    protected function getFilesFromDir($folder_path)
    {
        $files = array();
        if ($handle = opendir($folder_path)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    if(is_dir($folder_path . DIRECTORY_SEPARATOR . $file)) {
                        $dir2 = $folder_path . DIRECTORY_SEPARATOR . $file;
                        $files[] = $this->getFilesFromDir($dir2);
                    } else {
                        //$files[] = pathinfo($file, PATHINFO_FILENAME);
                        $files[] = $folder_path . DIRECTORY_SEPARATOR . $file;
                    }
                }
            }
            closedir($handle);
        }
        return $this->arrayFlat($files);
    }
    
    /**
     * Converts a multidimensional array into a monodimensional one
     * 
     * @param array $array
     * @return array
     */
    protected function arrayFlat($array)
    {
        $tmp = array();
        foreach($array as $a) {
            if(is_array($a)) {
                $tmp = array_merge($tmp, $this->arrayFlat($a));
            } else {
                $tmp[] = $a;
            }
        }
        return $tmp;
    }
    
    /**
     * Removes comments from a SQL string
     *  
     * @param string $sql
     * return string
     */
    protected function stripComments(string $sql)
    {
        $str= preg_replace('!/\*.*?\*/!s', '', $sql);
        $str = preg_replace('/\n\s*\n/', "\n", $str);
        return $str;
    }
    
    protected function getInstallerApp()
    {
        return $this->getWorkbench()->getCoreApp();
    }
    
}
?>