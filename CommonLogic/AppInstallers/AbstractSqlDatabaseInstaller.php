<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Events\Installer\OnInstallEvent;
use exface\Core\Interfaces\Selectors\DataSourceSelectorInterface;
use exface\Core\CommonLogic\Selectors\DataSourceSelector;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Factories\DataSourceFactory;
use exface\Core\Exceptions\DataSources\DataSourceHasNoConnectionError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\RegularExpressionDataType;
use exface\Core\DataTypes\JsonDataType;

/**
 * This creates and manages SQL databases and performs SQL updates.
 *
 * If the app has it's own SQL database (= is not built on top of an existing
 * data source), changes to the SQL schema must go hand-in-hand with changes of
 * the meta model and the code. This installer takes care of migrating the schema
 * by performing SQL scripts stored in a special folder within the app (by
 * default "install/Sql/%Database_Version").
 * 
 * ## How does int work?
 * 
 * The installer can basically do the following things:
 * 
 * 1. Create a database according to the given data connection (if it does not exist)
 * 2. Execute migrations, that were not applied to the DB schema yet and take down those,
 * that were applied, but are not required anymore.
 * 3. Execute any SQL within files in specified folders - in constrast to migrations,
 * this SQL will be executed on every install, regardless of whether it was executed
 * before or not.
 * 
 * See documentation > Developers Docs > App Installers > SQL Database Installer for 
 * detailed instructions.
 * 
 * ## Configuration
 * 
 * By default, this installer offers the following configuration options to control
 * it's behavior on a specific installation. These options can be added to the config
 * of the app being installed.
 * 
 * - `INSTALLER.SQLDATABASEINSTALLER.DISABLED` - set to TRUE to disable this installer
 * completely (e.g. if you wish to manage the database manually).
 * - `INSTALLER.SQLDATABASEINSTALLER.SKIP_MIGRATIONS` - array of migration names to
 * skip for this specific installation.
 * 
 * If an app contains multiple SQL-installers, the config option namespace may be changed
 * when instantiatiating the installer via setConfigOptionNamePrefix(), so that each 
 * installer can be configured separately. If not done so, each option will affect
 * all SQL-installers. 
 *
 * ## How to add an SqlDatabaseInstaller to your app:
 * 
 * 1) Make sure, your app includes the following folder structure: Install/Sql/%SqlDbType%/
 * 2) Write specific Installer for your Database Version (for example MySQL) extending this Abstract Class
 * and add that Installer to the getInstaller() method of your app similar to as follows:
 *
 
 // ...preceding installers here...
        
        $schema_installer = new MySqlDatabaseInstaller($this->getSelector());
        $schema_installer
            ->setDataSourceSelector('uid_of_target_data_source')
            ->setFoldersWithMigrations(['InitDB','Migrations', 'DemoData'])
            ->setFoldersWithStaticSql(['Views']);
        $installer->addInstaller($schema_installer);
 
 // ...subsequent installers here...
 
 return $installer;
 }
 
 * 3) Change the setFoldersWithMigrations array and the setFoldersWitStatcSql fitting
 * to your folder structure in Install/Sql/%SqlDbType%/ 
 *
 * ## Transaction handling
 * 
 * The abstract installer does not handle transactions. Transactions must be started/committed in
 * the concrete implementations as not all DBMS support transactional DDL statements.
 * 
 * @link Docs/developer_docs/App_installers/SQL_Database_Installer.md
 * 
 * @author Ralf Mulansky
 *
 */
abstract class AbstractSqlDatabaseInstaller extends AbstractAppInstaller
{
    const CONFIG_OPTION_SKIP_MIGRATIONS = 'SKIP_MIGRATIONS';
    const CONFIG_OPTION_DISABLED = 'DISABLED';
    
    private $sql_folder_name = 'Sql';
        
    private $sql_uninstall_folder_name = 'uninstall';
    
    private $data_connection = null;
    
    private $dataSourceSelector = null;
    
    private $sql_migration_folders = [];
    
    private $sql_static_folders = [];
    
    private $sql_migrations_table = '_migrations';
    
    private $configOptionNamePrefix = 'INSTALLER.SQLDATABASEINSTALLER.';
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path) : \Iterator
    {
        $indent = $this->getOutputIndentation();
        if ($this->isDisabled() === true) {
            yield $indent . 'SQL installer disabled' . PHP_EOL;
            return;
        } else {
            yield $indent . 'SQL installer:' . PHP_EOL;
        }
        
        yield from $this->installDatabase($this->getDataConnection(), $indent.$indent);
        yield from $this->installMigrations($source_absolute_path, $indent.$indent);
        yield from $this->installStaticSql($source_absolute_path, $indent.$indent);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnInstallEvent($this));
        
        return;
    }
    
    /**
     * 
     * @param string $source_absolute_path
     * @return string
     */
    protected function installStaticSql(string $source_absolute_path, string $indent = '') : \Iterator
    {
        yield from $this->runSqlFromFilesInFolder($source_absolute_path, $this->getFoldersWithStaticSql(), $indent);
    }
    
    /**
     *
     * @param string $source_absolute_path
     * @param string $indent
     * @return string
     */
    protected function installMigrations(string $source_absolute_path, string $indent = '') : \Iterator
    {
        yield $indent . 'SQL migrations:';
        
        $indent2 = PHP_EOL . $indent . $this->getOutputIndentation();
        $connection = $this->getDataConnection();
        $migrationsInApp = $this->getMigrationsFromApp($source_absolute_path);
        $migrationsInDB = $this->getMigrationsFromDb($connection);
        $cnt = 0;

        foreach ($this->getDownMigrations($migrationsInDB, $migrationsInApp) as $migration) {
            if (! $migration->hasDownScript()) {
                yield $indent2 . 'DOWN migration ' . $migration->getMigrationName() . ' not required - migration has no down-script';
                continue;
            }
            try {
                $this->migrateDown($migration, $connection);
                $cnt++;
                yield $indent2 . 'DOWN migration ' . $migration->getMigrationName();
            } catch (\Throwable $e) {
                $this->migrateFail($migration, $connection, false, $e);
                yield $indent2 . 'FAILED DOWN migration ' . $migration->getMigrationName() . ' failed! ' . $e->getMessage() . ' - see SQL migration logs for troubleshooting.' . PHP_EOL;
                throw new InstallerRuntimeError($this, 'SQL migration ' . $migration->getMigrationName() . ' failed! ' . $e->getMessage() . ' - see SQL migration logs for troubleshooting.', null, $e);
            }
        }
        foreach ($this->getUpMigrations($migrationsInDB, $migrationsInApp) as $migration) {
            try {
                $this->migrateUp($migration, $connection);
                $cnt++;
                yield $indent2 . 'UP migration ' . $migration->getMigrationName();
            } catch (\Throwable $e) {
                $this->migrateFail($migration, $connection, true, $e);
                yield  $indent2 . 'FAILED UP migration ' . $migration->getMigrationName() . ' failed! ' . $e->getMessage() . ' - see SQL migration logs for troubleshooting.' . PHP_EOL;
                throw new InstallerRuntimeError($this, 'SQL migration ' . $migration->getMigrationName() . ' failed! ' . $e->getMessage() . ' - see SQL migration logs for troubleshooting.', null, $e);
            }
        }
        
        if ($cnt === 0) {
            yield ' not needed';
        }
        
        yield PHP_EOL;
        
        return;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall() : \Iterator
    {
        yield 'SQL schema uninstaller not implemented for ' . $this->getSelectorInstalling()->toString() . '!' . PHP_EOL;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $destination_absolute_path) : \Iterator
    {
        yield 'SQL Backup not implemented for installer "' . $this->getSelectorInstalling()->toString() . '"!' . PHP_EOL;
    }
    
    /**
     * Method to check if Database already exists, if not, needs to create it.
     * Custom for every SQL Database Type.
     * 
     * @param SqlDataConnectorInterface
     * @return string
     */
    abstract protected function installDatabase(SqlDataConnectorInterface $connection, string $indent = '') : \Iterator;
    
    /**
     * Returns foldername containing subfolders for SQL Database Types.
     *
     * Default: 'Sql'
     *
     * @return string
     */
    public function getSqlFolderName() : string
    {
        return $this->sql_folder_name;
    }
    
    /**
     * Function to set folder name which contains subfolders for SQL Database Types.
     * 
     * Default: 'Sql'
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
     * Returns absolute path to Sql folder
     * 
     * Default: %app_folder%/Install/Sql
     *
     * @param string $source_absolute_path
     * @return string
     */
    public function getSqlFolderAbsolutePath(string $source_absolute_path) : string
    {
        return $this->getInstallFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . $this->getSqlFolderName();
    }
    
    /**
     * Returns Data Connection
     *
     * @return SqlDataConnectorInterface
     */
    public function getDataConnection() : SqlDataConnectorInterface
    {
        if ($this->data_connection === null) {
            try {
                $sourceSelector = $this->getDataSourceSelector();
            } catch (\Throwable $e) {
                throw new InstallerRuntimeError($this, 'Invalid configuration of the SQL installer in app "' . $this->getSelectorInstalling()->toString() . '": neither a data connection nor a valid data source selector were provided!');
            }
            $dataSource = DataSourceFactory::createFromModel($this->getWorkbench(), $sourceSelector);
            try {
                $this->data_connection = $this->checkDataConnection($dataSource->getConnection());
            } catch (DataSourceHasNoConnectionError $e) {
                throw new InstallerRuntimeError($this, 'Cannot install SQL DB for app "' . $this->getSelectorInstalling()->toString() . '": please provide a valid connection for data source "' . $dataSource->getName() . '" and reinstall/repair the app.', '77UP8Q4', $e);
            }
        }
        return $this->data_connection;
    }
    
    /**
     * 
     * @param SqlDataConnectorInterface $connection
     * @return SqlDataConnectorInterface
     */
    protected abstract function checkDataConnection(SqlDataConnectorInterface $connection) : SqlDataConnectorInterface;
    
    /**
     * Set the connection to the SQL database explicitly instead of setDataSourceSelector().
     * 
     * Setting an explicit data source can be usefull if that data source is being reused
     * and you are sure, that it is instantiated already when the installer is initialized.
     * This is basically only the case for built-in core connections or those not needing any
     * configuration like the local file system.
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
     * Returns the value of a configuration option - one of the CONFIG_OPTION_xxx constants.
     * 
     * This method automatically uses the config option prefix (namespace) of this
     * installer.
     * 
     * @param string $name
     * @return string
     */
    protected function getConfigOption(string $name) : ?string
    {
        $config = $this->getApp()->getConfig();
        $option = $this->configOptionNamePrefix . $name;
        if ($config->hasOption($option)) {
            return $config->getOption($option);
        } else {
            return null;
        }
    }
    
    /**
     * Changes the prefix of the configuration keys for this installer.
     * 
     * The default is `INSTALLER.SQLDATABASEINSTALLER.`
     * 
     * This is usefull if you have multiple SQL installers in a single app and wish
     * to configure them separately.
     *
     * @param string $value
     * @return AbstractSqlDatabaseInstaller
     */
    public function setConfigOptionNamePrefix(string $value)
    {
        $this->configOptionNamePrefix = $value;
        return $this;
    }
    
    /**
     * Gets the array with the file names for the migrations that should be skipped
     * during installation from the config file.
     * 
     * @return array
     */
    protected function getSqlMigrationsToSkip() : array
    {
        $uxon = $this->getConfigOption(static::CONFIG_OPTION_SKIP_MIGRATIONS);
        if ($uxon instanceof UxonObject) {
            return $uxon->toArray();
        } else {
            return [];
        }
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
     * Function to set the folders which contain Sql files that should be migrated
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
     * Function to set folders which contain static Sql files, meaning files which contain Sql Statements
     * that should be run on every installation or update
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
     * Returns the name of the SQL table to store the migration log.
     * 
     * @return string
     */
    public function getMigrationsTableName() : string
    {
        return $this->sql_migrations_table;
    }
    
    /**
     * Changes the migration table name to a custom value (default is '_migrations').
     * 
     * NOTE: you MUST use a custom table name if you have multiple installers operating 
     * on the same database!
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
     * Returns the SQL dialect name (e.g. used as subfolder name)
     * 
     * @return string
     */
    abstract protected function getSqlDbType() : string;
    
    /**
     * Returns the string, that flags the beginning of the UP-script in a migration.
     * 
     * Override this method to define a custom marker for a specific SQL dialect.
     * 
     * @return string
     */
    protected function getMarkerUp() : string
    {
        return '-- UP';
    }
    
    /**
     * Returns the string, that flags the beginning of the DOWN-script in a migration.
     * 
     * Override this method to define a custom marker for a specific SQL dialect.
     * 
     * @return string
     */
    protected function getMarkerDown() : string
    {
        return '-- DOWN';
    }
    
    /**
     * Returns the string, that sets a custom batch delimiter for a script.
     * 
     * Override this method to define a custom marker for a specific SQL dialect.
     * 
     * @return string
     */
    protected function getMarkerBatchDelimiter() : string
    {
        return '-- BATCH-DELIMITER';
    }
    
    /**
     * Function to perform migrations on the database.
     * 
     * @param SqlMigration $migration
     * @param SqlDataConnectorInterface $connection
     * @return bool
     */
    abstract protected function migrateDown(SqlMigration $migration, SqlDataConnectorInterface $connection) : SqlMigration;
    
    /**
     * Function to rollback migrations in Database
     * 
     * @param SqlMigration $migration
     * @param SqlDataConnectorInterface $connection
     * @return bool
     */
    abstract protected function migrateUp(SqlMigration $migration, SqlDataConnectorInterface $connection): SqlMigration;

    /**
     * 
     * 
     * @param SqlMigration $migration
     * @param SqlDataConnectorInterface $connection
     * @param bool $up
     * @param \Throwable $exception
     * @return SqlMigration
     */
    abstract protected function migrateFail(SqlMigration $migration, SqlDataConnectorInterface $connection, bool $up, \Throwable $exception) : SqlMigration;
    
    /**
     * Function to get all on the database currently applied migrations
     *
     * @param SqlDataConnectorInterface $connection
     * @return SqlMigration[]
     */
    abstract protected function getMigrationsFromDb(SqlDataConnectorInterface $connection): array;

    /**
     * Iterates through the files in "%source_absolute_path%/%install_folder_name%/%sql_folder_name%/%SqlDbType%/%folders%"
     * and all subfolders attempting to execute the SQL scripts stored in those files
     *
     * @param string $source_absolute_path
     * @param string $folder_name
     * @return string
     */
    protected function runSqlFromFilesInFolder(string $source_absolute_path, array $folders, string $indent): \Iterator
    {
        yield $indent . 'Static SQL:';
        
        $indent2 = PHP_EOL . $indent . $this->getOutputIndentation();
        
        $files = $this->getFiles($source_absolute_path, $folders);
        foreach ($files as $file){
            $sql = file_get_contents($file);
            $sql = $this->stripComments($sql);
            $connection = $this->getDataConnection();
            try {
                $this->runSqlMultiStatementScript($connection, $sql);
                $this->getWorkbench()->getLogger()->debug('SQL script ' . $file . ' executed successfully ');
                yield $indent2 . 'UP ' . FilePathDataType::findFileName($file, true);
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e);
                $filename = FilePathDataType::findFileName($file, true);
                yield $indent2 . 'ERROR in ' . $filename . ': ' . $e->getMessage();
            }
        }
        
        if (empty($files)) {
            yield ' not needed';
        }
        
        yield PHP_EOL;
        
        return;
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
        $files = [];
        foreach ($folders as $folder_name){
            $folder_path = $this->getSqlFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . $this->getSqlDbType() . DIRECTORY_SEPARATOR . $folder_name;
            if (is_dir($folder_path) === true) {
                $files=array_merge($files, $this->getFilesFromDir($folder_path));
            }
        }
        return $files;
    }
   
    /**
     * Gets all migrations in the migration folders specified in the installer of the app
     * 
     * @param string $source_absolute_path
     * @return SqlMigration[]
     */
    protected function getMigrationsFromApp(string $source_absolute_path): array
    {
        $migrs = [];
        foreach ($this->getFiles($source_absolute_path, $this->getFoldersWithMigrations()) as $path) {
            $file_content = file_get_contents($path);
            $migrationName = $this->transformFilepathToMigrationName($path);
            $migrs[] = new SqlMigration($migrationName, $this->getMigrationScript($migrationName, $file_content), $this->getMigrationScript($migrationName, $file_content, false));
        }
        return $migrs;
    }

    /**
     * Builds an array containing items that are in the App but not in the DB.
     *
     * @param SqlMigration[] $migrations_in_db
     * @param SqlMigration[] $migrations_in_app
     * @return SqlMigration[]
     */
    protected function getUpMigrations(array $migrations_in_db, array $migrations_in_app): array
    {
        if (empty($migrations_in_db)) {
            return $migrations_in_app;
        }
        $arr = array();
        foreach ($migrations_in_app as $mApp) {
            $present = false;
            foreach ($migrations_in_db as $mDb) {
                if ($mApp->equals($mDb)) {
                    $present = true;
                    if (!$mDb->isSkipped()) {
                        //if migration is not marked as skipped, evalutate further
                        if ($mDb->isFailed() && ! $mDb->isDown()) {
                            // There was an error on the last execution of the UP-script and the script is not skipped or downed.
                            // Migration will be exectued again.
                            $arr[] = $mDb->setUpScript($mApp->getUpScript())->setDownScript($mApp->getDownScript());
                        }
                        if (!$mDb->isFailed() && $mDb->isDown()) {
                            // Latest status of this migration is that it is down and not marked as skipped.
                            // Reinstallation of the migration. 
                            $arr[] = $mDb->setUpScript($mApp->getUpScript())->setDownScript($mApp->getDownScript());
                        }
                    }
                    break;
                }
            }
            // if migration has no entry in database yet, UP script will be run
            if ($present === false) {
                $arr[] = $mApp;
            }
        }
        return $arr;
    }

    /**
     * Builds an array containing items that are in the DB but not in the App.
     *
     * @param SqlMigration[] $migrations_in_db
     * @param SqlMigration[] $migrations_in_app
     * @return SqlMigration[]
     */
    protected function getDownMigrations(array $migrations_in_db, array $migrations_in_app): array
    {
        $arr = array();
        foreach ($migrations_in_db as $mDb) {
            $present = false;
            foreach ($migrations_in_app as $mApp) {
                if ($mDb->equals($mApp)) {
                    $present = true;
                    break;
                }
            }
            // The migration is not present in the app anymore, is `up` and is not marked as `skip` in the database.
            // The down script will be run.
            if ($present === false && $mDb->isUp() && !$mDb->isSkipped()) {
                $arr[] = $mDb;
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
    protected function getMigrationScript(string $filename, string $src, bool $up = true) : string
    {
        $length=strlen($src);
        $cut_down=stripos($src, $this->getMarkerDown());
        $cut_up=stripos($src, $this->getMarkerUp());
        if ($cut_down == FALSE){
            if ($up == TRUE){
                $migstr = $src;
            } elseif ($up == FALSE){
                $migstr = '';
                $this->getWorkbench()->getLogger()->warning("SQL migration {$filename} has no down-script!"); 
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
        return $this->stripLinebreaks($migstr);
    }
    
    /**
     * 
     * @param string $sql
     * @return string|NULL
     */
    protected function getBatchDelimiter(string $sql) : ?string
    {
        $matches = [];
        $found = preg_match('/' . $this->getMarkerBatchDelimiter() . ' (.*)/', $sql, $matches);
        if ($found && $matches[1] !== null) {
            $delim = trim($matches[1]);
            return $delim === '' ? null : $delim;
        }
        return null;
    }
    
    /**
     * Gets all files in the $folder_path folder and all subfolders, files are sorted ascending by name for each folder
     * returning them in a monodimensional array
     * 
     * @param string $folder
     * @return array
     */
    protected function getFilesFromDir(string $folder_path) : array
    {
        $files = [];
        $readFiles = scandir($folder_path, SCANDIR_SORT_ASCENDING);
        foreach ($readFiles as $file) {
            if ($file != "." && $file != "..") {
                if (is_dir($folder_path . DIRECTORY_SEPARATOR . $file)) {
                    $dir2 = $folder_path . DIRECTORY_SEPARATOR . $file;
                    $files[] = $this->getFilesFromDir($dir2);
                } else {
                    //$files[] = pathinfo($file, PATHINFO_FILENAME);
                    if (in_array($file,$this->getSqlMigrationsToSkip()) == FALSE){
                        $files[] = $folder_path . DIRECTORY_SEPARATOR . $file;
                    }
                }
            }
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
    
    /***
     * Runs multiple SQL statements, wrapping them in a transaction when $wrapInTransaction set to TRUE.
     * 
     * @param SqlDataConnectorInterface $connection
     * @param string $script
     * @param bool $wrapInTransaction
     * @throws \Throwable
     * @return array
     */
    protected function runSqlMultiStatementScript(SqlDataConnectorInterface $connection, string $script, bool $wrapInTransaction = false) : array
    {
        $results = [];
        try {
            if ($wrapInTransaction === true) {
                $connection->transactionStart();
            }
            if (null !== $delim = $this->getBatchDelimiter($script)) {
                if (! RegularExpressionDataType::isRegex($delim)) {
                    $delim = '/' . preg_quote($delim, '/') . '/';
                }
                foreach (preg_split($delim, $script) as $statement) {
                    if ($statement) {
                        $results[] = $connection->runSql($statement, true);
                    }
                }
            } else {
                $results[] = $connection->runSql($script, true);
            }
            
            if ($wrapInTransaction === true) {
                $connection->transactionCommit();
            }
        } catch (\Throwable $e) {
            foreach ($results as $query) {
                $query->freeResult();
            }
            
            if ($wrapInTransaction === true) {
                $connection->transactionRollback();
            }
            throw $e;
        }
        
        return $results;
    }
    
    /**
     *
     * @param SqlDataQuery[] $sqlDataQueries
     * @return string
     */
    protected function stringifyQueryResults(array $sqlDataQueries) : string
    {
        $json = [];
        foreach ($sqlDataQueries as $query) {
            $resultArray = $query->getResultArray();
            if (empty($resultArray)) {
                $result = "No result for SQL Statement given!";
            } else {
                $result = $resultArray;
            }
            $json[] = [
                "SQL" => $query->getSql(),
                "Result" => $result
            ];
        }
        return JsonDataType::encodeJson($json, true);
    }
    
    /**
     * Removes comments from a SQL string
     *  
     * @param string $sql
     * @return string
     */
    protected function stripComments(string $sql) : string
    {
        return preg_replace('!/\*.*?\*/!s', '', $sql);
    }
    
    /**
     * Removes linebreaks from a SQL string
     * 
     * @param string $sql
     * @return string
     */
    protected function stripLinebreaks(string $sql) : string
    {
        return preg_replace('/\n\s*\n/', "\n", $sql);
    }
    
    /**
     * Transforms the given $path to the migration name by cutting of the beginning
     * of the $path string till the SqlDbType folder
     * 
     * @param string $path
     * @return string
     */
    protected function transformFilepathToMigrationName (string $path) : string
    {
        $length=strlen($path);
        $cut=strpos($path, $this->getSqlDbType());
        $file_str = substr($path, $cut, ($length-$cut));
        $file_str = FilePathDataType::normalize($file_str, '/');
        return $file_str;
    }
    
    protected function getInstallerApp()
    {
        return $this->getWorkbench()->getCoreApp();
    }  
    
    /**
     * Returns TRUE if this installer was disabled in the configuration of the installed app.
     * 
     * @return bool
     */
    protected function isDisabled() : bool
    {
        return $this->getConfigOption(static::CONFIG_OPTION_DISABLED) ?? false;
    }
    
    /**
     *
     * @return DataSourceSelectorInterface
     */
    protected function getDataSourceSelector() : DataSourceSelectorInterface
    {
        return $this->dataSourceSelector;
    }
    
    /**
     * The connection of this data source will be used to perform installer operations.
     * 
     * Alternatively to passing the data source selector, you can also set the
     * desired connection directly via setDataConnection(). The latter is advantageous
     * if you definitely know, that the data source was already instantiated and
     * has a valid connection at the time of instantiating the installer. This is
     * basically only the case for built-in core connections or those not needing any
     * configuration like the local file system.
     * 
     * @param string|DataSourceSelectorInterface $value
     * @return AbstractSqlDatabaseInstaller
     */
    public function setDataSourceSelector($value) : AbstractSqlDatabaseInstaller
    {
        if (! $value instanceof DataSourceSelectorInterface) {
            $value = new DataSourceSelector($this->getWorkbench(), $value);
        }
        $this->dataSourceSelector = $value;
        return $this;
    }
}
?>