<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\AppInstallerInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractAppInstaller extends AbstractNameResolverInstaller implements AppInstallerInterface
{

    private $app = null;

    private $install_folder_name = 'Install';

    /**
     *
     * @return \exface\Core\Interfaces\AppInterface
     */
    public function getApp()
    {
        if (is_null($this->app)) {
            $this->app = $this->getWorkbench()->getApp($this->getNameResolver()->getAliasWithNamespace());
        }
        return $this->app;
    }

    /**
     * Returns the absolute path to the folder containing installation files for this app.
     * By default it is %app_folder%/Install.
     *
     * @return string
     */
    public function getInstallFolderAbsolutePath($source_absolute_path)
    {
        return $source_absolute_path . DIRECTORY_SEPARATOR . $this->getInstallFolderName();
    }

    /**
     * Returns the path to the folder containing installation files for this app relative to the app folder.
     * Default: "Install".
     *
     * @return string
     */
    public function getInstallFolderName()
    {
        return $this->install_folder_name;
    }

    /**
     * Changes the name of the folder, that contains installation files for this app.
     *
     * @param string $path_relative_to_app_folder            
     * @return \exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller
     */
    public function setInstallFolderName($path_relative_to_app_folder)
    {
        $this->install_folder_name = $path_relative_to_app_folder;
        return $this;
    }
}