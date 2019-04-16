<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;

/**
 * This creates and manages SQL databases and performs SQL updates.
 *
 * If the app has it's own SQL database (= is not built on top of an existing
 * data source), changes to the SQL schema must go hand-in-hand with changes of
 * the meta model and the code. This installer takes care of migrating the schema
 * by performing SQL scripts stored in a special folder within the app (by
 * default "install/Sql/%Database_Version").
 *
 * ## How to add an SqlDatabaseInstaller to your app:
 * 
 * 1) Make sure, your app includes the following folder structure: Install/Sql/%SqlDbType%/
 * 2) Write specific Installer for your Database Version (for example MySQL) extending this Abstract Class
 * and add that Installer to the getInstaller() method of your app as follows:
 *
 
 // ...receding installers here...
        
        $schema_installer = new MySqlDatabaseInstaller($this->getSelector());
        $schema_installer
            ->setFoldersWithMigrations(['InitDB','Migrations', 'DemoData'])
            ->setFoldersWithStaticSql(['Views']);
        try {
            $schema_installer->setDataConnection(...);
            $installer->addInstaller($schema_installer);
        } catch (MetaObjectNotFoundError $e) {
            $this->getWorkbench()->getLogger()->warning('Cannot init SQLDatabaseInstaller for app ' . $this->getAliasWithNamespace() . ': no model there yet!');
        }
 
 // ...subsequent installers here...
 
 return $installer;
 }
 
 * 3) Change the setFoldersWithMigrations array and the setFoldersWitStatcSql fitting
 * to your folder structur in Install/Sql/%SqlDbType%/ 
 *
 *
 * ## Transaction handling
 * 
 * The abstract installer does not handle transactions. Transactions must be started/committed in
 * the concrete implementations as not all DBMS support transactional DDL statements.
 * 
 * @author Ralf Mulansky
 *
 */
abstract class AbstractSqlDatabaseInstaller extends AbstractAppInstaller
{
    private $sql_folder_name = 'Sql';
        
    private $sql_uninstall_folder_name = 'uninstall';
    
    private $data_connection = null;
    
    private $sql_migration_folders = [];
    
    private $sql_static_folders = [];
    
    private $sql_migrations_table = '_migrations';
    
    /**
     *
     * {@inheritDoc}
     * 
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install($source_absolute_path) : string
    {
        //TODO Test ob Db-Schema existiert
        //$result = $this->runSqlFromFilesInFolder($source_absolute_path, $this->getSqlInitDbFolderName());
        $result .= $this->installMigrations($source_absolute_path);
        $result .= $this->installStaticSql($source_absolute_path);
        return $result;
    }
    
    /**
     * 
     * @param string $source_absolute_path
     * @return string
     */
    protected function installStaticSql(string $source_absolute_path) : string
    {
        return $this->runSqlFromFilesInFolder($source_absolute_path, $this->getFoldersWithStaticSql());
    }
    
    /**
     * 
     * @param string $source_absolute_path
     * @return string
     */
    protected function installMigrations(string $source_absolute_path) : string
    {
        $migrationsInApp = $this->getMigrationsFromApp($source_absolute_path);
        $migrationsInDB = $this->getMigrationsFromDb($this->getDataConnection());
        $migratedUp = 0;
        $migratedDown = 0;
        
        foreach ($this->diffMigrations($migrationsInDB, $migrationsInApp) as $migration) {
            $this->migrateDown($migration, $this->getDataConnection());
            $migratedDown++;
        }
        foreach ($this->diffMigrations($migrationsInApp, $migrationsInDB) as $migration) {
            $this->migrateUp($migration, $this->getDataConnection());
            $migratedUp++;
        }
        
        if ($migratedDown === 0 && $migratedUp === 0) {
            $message = ' not needed';
        } else {
            $message = ($migratedUp > 0 ? ' ' . $migratedUp . ' UP' : '') . ($migratedDown > 0 ? ' ' . $migratedDown . ' DOWN' : '');
        }
        
        return ' SQL migrations:' . $message;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : string
    {
        return 'Automatic uninstaller not implemented for' . $this->getSelectorInstalling()->getAliasWithNamespace() . '!';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup($destination_absolute_path) : string
    {
        return 'SQL Backup not implemented for installer "' . $this->getSelectorInstalling()->getAliasWithNamespace() . '"!';
    }
    
    /**
     *
     * @return string
     */
    public function getSqlFolderName() : string
    {
        return $this->sql_folder_name;
    }
    
    /**
     *
     * @param string $value
     * @return $this
     */
    public function setSqlFolderName(string $value) : AbstractSqlDatabaseInstaller
    {
        $this->sql_folder_name = $value;
        return $this;
    }
    
    /**
     * Default: %app_folder%/Install/sql
     *
     * @return string
     */
    public function getSqlFolderAbsolutePath(string $source_absolute_path) : string
    {
        return $this->getInstallFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . $this->getSqlFolderName();
    }
    
    /**
     *
     * @return SqlDataConnectorInterface
     */
    public function getDataConnection() : SqlDataConnectorInterface
    {
        return $this->data_connection;
    }
    
    /**
     * 
     * @param SqlDataConnectorInterface $value
     * @return AbstractSqlDatabaseInstaller
     */
    public function setDataConnection(SqlDataConnectorInterface $value) : AbstractSqlDatabaseInstaller
    {
        $this->data_connection = $value;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getFoldersWithMigrations() : array
    {
        return $this->sql_migration_folders;
    }
    
    /**
     * 
     * @param array $pathsRelativeToSqlFolder
     * @return AbstractSqlDatabaseInstaller
     */
    public function setFoldersWithMigrations(array $pathsRelativeToSqlFolder) : AbstractSqlDatabaseInstaller
    {
        $this->sql_migration_folders = $pathsRelativeToSqlFolder;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getFoldersWithStaticSql() : array
    {
        return $this->sql_static_folders;
    }
    
    /**
     * 
     * @param array $pathsRelativeToSqlFolder
     * @return AbstractSqlDatabaseInstaller
     */
    public function setFoldersWithStaticSql(array $pathsRelativeToSqlFolder) : AbstractSqlDatabaseInstaller
    {
        $this->sql_static_folders = $pathsRelativeToSqlFolder;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getMigrationsTableName() : string
    {
        return $this->sql_migrations_table;
    }
    
    /**
     * 
     * @param string $migrations_table_name
     * @return AbstractSqlDatabaseInstaller
     */     
    public function setMigrationsTableName(string $migrations_table_name) : AbstractSqlDatabaseInstaller
    {
        $this->sql_migrations_table = $migrations_table_name;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    abstract protected function getSqlDbType() : string;
    
    /**
     *
     * @return string
     */
    protected function getMarkerUp() : string
    {
        return '-- UP';
    }
    
    /**
     * 
     * @return string
     */
    protected function getMarkerDown() : string
    {
        return '-- DOWN';
    }
    
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
     *
     * @param SqlDataConnectorInterface $connection
     * @return SqlMigration[]
     */
    abstract protected function getMigrationsFromDb(SqlDataConnectorInterface $connection) : array;
               

    /**
     * Iterates through the files in "%source_absolute_path%/%install_folder_name%/%sql_folder_name%/%SqlDbType%/%folders%"
     * and all subfolders attempting to execute the SQL scripts stored in those files
     *
     * @param string $source_absolute_path
     *        string $folder_name
     * @return string
     */    
    protected function runSqlFromFilesInFolder(string $source_absolute_path, array $folders) : string
    {
        $files = $this->getFiles($source_absolute_path, $folders);
        $result = ' No SQL Files found!';
        foreach ($files as $file){
            $sql = file_get_contents($file);
            $sql = $this->stripComments($sql);
            $connection = $this->getDataConnection();
            try {
                $this->runSqlMultiStatementScript($connection, $sql);
                $this->getWorkbench()->getLogger()->debug('SQL script ' . $file . ' executed successfully ');
                $result = ' Static Sql files: Performing SQL succeded ';
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
                $result = ' Static Sql files: Performing SQL failed ';
            }
        }
        return $result;
    }
       
    /**
     * Gets all files in the folder %source_absolute_path%/%install_folder_name%/%sql_folder_name%/%SqlDbType%/%folders%
     * and all subfolders
     * 
     * @param string $source_absolute_path
     * @param array $folders
     * @return array
     */
    protected function getFiles(string $source_absolute_path, array $folders) : array
    {
        $files = array();
        foreach ($folders as $folder_name){
            $folder_path = $this->getSqlFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . $this->getSqlDbType() . DIRECTORY_SEPARATOR . $folder_name;
            if (is_dir($folder_path) === false) {
                return $files;
            }
            $files=array_merge($files,$this->getFilesFromDir($folder_path));
        }
        return $files;
    }
   
    /**
     * Gets all migrations in the migration folders specified in the installer of the app
     * 
     * @param string $source_absolute_path
     * @return SqlMigration[]
     */
    protected function getMigrationsFromApp(string $source_absolute_path) : array
    {
        $migrs = [];
        foreach ($this->getFiles($source_absolute_path, $this->getFoldersWithMigrations()) as $path) {
            $file_content = file_get_contents($path);
            $migrs[] = new SqlMigration($this->transformFilepathToMigrationName($path), $this->getMigrationScript($file_content), $this->getMigrationScript($file_content, false));
        }
        return $migrs;
    }
    
    /**
     * Builds an array containing items that are in $migrations_base but are not in $migrations_substract
     * 
     * @param SqlMigration[] $migrations_base
     * @param SqlMigration[] $migrations_substract
     * @return SqlMigration[]
     */
    protected function diffMigrations(array $migrations_base, array $migrations_substract) : array
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
     * Cuts the input string at the down-marker occurence
     * and gives back either the part before that or from that point on
     * if there is no down-marker occurence gives back the whole script
     * 
     * @param string $src
     * @param bool $up
     * @return string
     */
    protected function getMigrationScript(string $src, bool $up = true) : string
    {
        $length=strlen($src);
        $cut_down=strpos($src, $this->getMarkerDown());
        $cut_up=strpos($src, $this->getMarkerUp());
        if ($cut_down == FALSE){
            if ($up == TRUE){
                $migstr = $src;
            } elseif ($up == FALSE){
                $migstr = '';
                $this->getWorkbench()->getLogger()->error('SQL Script hat kein Down-Script! '); 
            }                       
        }
        else{
            if ($up === true){
                if ($cut_down > $cut_up){
                    $migstr = substr($src, 0, $cut_down);
                } elseif($cut_down < $cut_up){
                    $migstr = substr($src, $cut_up, ($length - $cut_up));
                }
            } elseif($up === false){
                if ($cut_down > $cut_up){
                    $migstr = substr($src, $cut_down, $length - $cut_down);
                } elseif($cut_down < $cut_up){
                    $migstr = substr($src, 0, $cut_down);
                }
            }                
        }
        return $this->stripComments($migstr);
    }
    
    /**
     * Gets all files in the $folder_path folder and all subfolders
     * returning them in a monodimensional array
     * 
     * @param string $folder
     * @return array
     */
    protected function getFilesFromDir(string $folder_path) : array
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
    protected function arrayFlat(array $array) : array
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
     * Runs multiple SQL statements, wrapping them in a transaction when $wrapInTransaction set to TRUE
     * 
     * @param SqlDataConnectorInterface $connection
     * @param string $script
     * @return string
     */
    protected function runSqlMultiStatementScript (SqlDataConnectorInterface $connection, string $script, bool $wrapInTransaction = false) : array
    {
        $result = [];
        try {
            if ($wrapInTransaction === true) {
                $connection->transactionStart();
            }
            
            foreach (preg_split("/;\R/", $script) as $statement) {
                if ($statement) {
                    $result[] = $connection->runSql($statement)->getResultArray();
                }
            }
            
            if ($wrapInTransaction === true) {
                $connection->transactionCommit();
            }
        } catch (\Throwable $e) {
            $connection->transactionRollback();
            throw $e;
        }
        
        return $result;
    }
    
    /**
     * Removes comments from a SQL string
     *  
     * @param string $sql
     * @return string
     */
    protected function stripComments(string $sql) : string
    {
        $str= preg_replace('!/\*.*?\*/!s', '', $sql);
        $str = preg_replace('/\n\s*\n/', "\n", $str);
        return $str;
    }
    
    /**
     * Transforms the given $path to the migration name by cutting of the beginning of the $path string till the SqlDbType folder
     * 
     * @param string $path
     * @return string
     */
    protected function transformFilepathToMigrationName (string $path) : string
    {
        $length=strlen($path);
        $cut=strpos($path, $this->getSqlDbType());
        $file_str = substr($path, $cut, ($length-$cut));
        return $file_str;
        
    }
    
    protected function getInstallerApp()
    {
        return $this->getWorkbench()->getCoreApp();
    }    
}
?>