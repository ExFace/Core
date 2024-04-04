<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * This creates and manages SQL databases and performs SQL updates.
 *
 * If the app has it's own SQL database (= is not built on top of an existing 
 * data source), changes to the SQL schema must go hand-in-hand with changes of 
 * the meta model and the code. This installer takes care of updating the schema 
 * by performing SQL scripts stored in a specifal folder within the app (by 
 * default "install/sql"). These scripts must follow a simple naming convention: 
 * they start with a number followed by a dot and a textual description. Update 
 * scripts are executed in the order of the leading number. The number of the 
 * last script executed is stored in the installation scope of the app's config, 
 * so the next time the installer runs, only new updates will get executed.
 * 
 * How to add an SqlSchemaInstaller to your app:
 * 1) Make sure, your app includes the following folder structure: Install/sql/updates
 * 2) Place your init scripts in Install/sql and your update scripts in the
 * updates subfolder. The scripts will be executed in alphabetic order. Pay
 * attention to the update naming convention described above.
 * 3) Add the SqlSchemaInstaller to the getInstaller() method of your app as
 * follows:
 * 
 * public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $installer = parent::getInstaller($injected_installer);
        
        // ...receding installers here...
        
        $schema_installer = new SqlSchemaInstaller($this->getSelectorInstalling());
        $schema_installer->setDataConnection(...);
        $installer->addInstaller($schema_installer);
        
        // ...subsequent installers here...
        
        return $installer;
    }
 *
 * @author Andrej Kabachnik
 *        
 */
class SqlSchemaInstaller extends AbstractAppInstaller
{

    private $sql_folder_name = 'sql';

    private $sql_updates_folder_name = 'updates';

    private $sql_install_folder_name = 'install';

    private $sql_uninstall_folder_name = 'uninstall';

    private $data_connection = null;
    
    private $last_update_id_config_option = 'INSTALLER.SQL_UPDATE_LAST_PERFORMED_ID';

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path) : \Iterator
    {
        yield from $this->update($source_absolute_path);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::update()
     */
    public function update($source_absolute_path)
    {
        yield $this->performModelSourceUpdate($source_absolute_path);
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
     * @param
     *            $value
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
    public function getSqlUpdatesFolderName()
    {
        return $this->sql_updates_folder_name;
    }

    /**
     *
     * @param
     *            $value
     * @return $this
     */
    public function setSqlUpdatesFolderName($value)
    {
        $this->sql_updates_folder_name = $value;
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
     * @param
     *            $value
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
     * Iterates through the files in "%source_absolute_path%/%install_folder_name%/%sql_folder_name%/%sql_updates_folder_name%" (by default
     * "%source_absolute_path%/Install/sql/updates") attempting to execute the SQL scripts stored in those files.
     * If anything goes wrong,
     * all subsequent files are not executed and the last successfull update is marked as performed. Thus, once the update is triggered
     * again, it will try to perform all the updates starting from the failed one.
     *
     * In order to explicitly skip one or more update files, increase the option INSTALLER.SQL_UPDATE_LAST_PERFORMED_ID in the local config
     * file of the app being installed to match the last update number that should not be installed.
     *
     * @param string $source_absolute_path            
     * @return string
     */
    protected function performModelSourceUpdate($source_absolute_path)
    {
        $updates_folder = $this->getSqlFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . $this->getSqlUpdatesFolderName();
        
        if (is_dir($updates_folder) === false) {
            return $this->getInstallerApp()->getTranslator()->translate('INSTALLER.SQLSCHEMA.NO_UPDATES');
        }
        try {
            $id_installed = $this->getApp()->getConfig()->getOption($this->getLastUpdateIdConfigOption());
        } catch (ConfigOptionNotFoundError $e){
            $id_installed = 0;
        }
        
        $updates_installed = array();
        $updates_failed = array();
        $error_text = '';
        
        $updateFolderDirScan = array_diff(scandir($updates_folder, SCANDIR_SORT_ASCENDING), array(
            '..',
            '.'
        ));
        foreach ($updateFolderDirScan as $file) {
            $id = intval(substr($file, 0, 4));
            if ($id > $id_installed) {
                if (count($updates_failed) > 0) {
                    $updates_failed[] = $id;
                    continue;
                }
                $this->getWorkbench()->getLogger()->debug('Installing SQL schema update No.' . $id);
                $sql = file_get_contents($updates_folder . DIRECTORY_SEPARATOR . $file);
                // Strip comments
                $sql= preg_replace('!/\*.*?\*/!s', '', $sql);
                $sql = preg_replace('/\n\s*\n/', "\n", $sql);
                try {
                    $this->getDataConnection()->transactionStart();
                    foreach (preg_split("/;\R/", $sql) as $statement) {
                        if ($statement) {
                            $this->getDataConnection()->runSql($statement);
                        }
                    }
                    $this->getDataConnection()->transactionCommit();
                    $updates_installed[] = $id;
                } catch (\Throwable $e) {
                    $updates_failed[] = $id;
                    $error_text = $e->getMessage() . ($e instanceof ExceptionInterface ? ' (log ID ' . $e->getId() . ')' : '');
                    $this->getWorkbench()->getLogger()->logException($e);
                }
            }
        }
        // Save the last id in order to skip installed ones next time
        if (count($updates_installed) > 0) {
            $this->setLastUpdateId(end($updates_installed));
        }
        
        if ($installed_counter = count($updates_installed)) {
            $result = $this->getInstallerApp()->getTranslator()->translate('INSTALLER.SQLSCHEMA.SUCCESS', array(
                '%counter%' => $installed_counter
            ), $installed_counter);
        }
        if ($failed_counter = count($updates_failed)) {
            $result_failed = $this->getInstallerApp()->getTranslator()->translate('INSTALLER.SQLSCHEMA.FAILED', array(
                '%counter%' => $failed_counter,
                '%first_failed_id%' => reset($updates_failed),
                '%error_text%' => $error_text
            ), $failed_counter);
        }
        
        if ($result && $result_failed) {
            $result = $result . '. ' . $result_failed;
        } elseif ($result_failed) {
            $result = $result_failed;
        }
        $result = $result ? " \n" . $result . '. ' : $result;
        
        return $result;
    }

    /**
     *
     * @param string $id            
     * @return $this
     */
    protected function setLastUpdateId($id)
    {
        $this->getApp()->getConfig()->setOption($this->getLastUpdateIdConfigOption(), $id, AppInterface::CONFIG_SCOPE_INSTALLATION); 
        return $this;
    }

    protected function getInstallerApp()
    {
        return $this->getWorkbench()->getCoreApp();
    }

    /**
     * Returns the configuration key used to store the last SQL update id in
     * the app, that this installer installs.
     * 
     * Default: INSTALLER.SQL_UPDATE_LAST_PERFORMED_ID
     * 
     * @return string
     */
    public function getLastUpdateIdConfigOption()
    {
        return $this->last_update_id_config_option;
    }

    /**
     * Changes the name of the configuration key to be used to store the last
     * SQL update id. The default is INSTALLER.LAST_PERFORMED_SQL_UPDATE_ID.
     * 
     * @param string $last_update_id_config_option
     * @return SqlSchemaInstaller
     */
    public function setLastUpdateIdConfigOption($string)
    {
        $this->last_update_id_config_option = $string;
        return $this;
    }
 
}
?>