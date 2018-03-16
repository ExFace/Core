<?php
namespace exface\Core;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;
use exface\Core\CommonLogic\Filemanager;

/**
 *
 * @method CoreApp getApp()
 *        
 * @author Andrej Kabachnik
 *        
 */
class CoreInstaller extends AbstractAppInstaller
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install($source_absolute_path)
    {
        $model_source_installer = $this->getWorkbench()->model()->getModelLoader()->getInstaller();
        return $model_source_installer->install($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $model_source_installer->getSelectorInstalling()->getFolderRelativeToVendorFolder());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::update()
     */
    public function update($source_absolute_path)
    {
        $model_source_installer = $this->getWorkbench()->model()->getModelLoader()->getInstaller();
        $result = $model_source_installer->update($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $model_source_installer->getSelectorInstalling()->getFolderRelativeToVendorFolder());
        $result .= $this->copyDefaultHtaccess($source_absolute_path);
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall()
    {
        return 'Uninstall not implemented for installer "' . $this->getSelectorInstalling()->getAliasWithNamespace() . '"!';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup($destination_absolute_path)
    {
        return 'Backup not implemented for' . $this->getSelectorInstalling()->getAliasWithNamespace() . '!';
    }
    
    protected function copyDefaultHtaccess($source_absolute_path)
    {
        $result = '';
        // Copy default .htaccess to the root of the installation
        $file = Filemanager::pathJoin([$this->getWorkbench()->getInstallationPath(), '.htaccess']);
        if (! file_exists($file)) {
            try {
                $this->getWorkbench()->filemanager()->copy($this->getInstallFolderAbsolutePath($source_absolute_path) . DIRECTORY_SEPARATOR . '.htaccess', $file);
                $result .= "\nGenerated default .htaccess file in plattform root";
            } catch (\Exception $e) {
                $result .= "\nFailed to copy default .htaccss file: " . $e->getMessage() . ' in ' . $e->getFile() . ' at ' . $e->getLine();
            }
        }
        
        return $result;
    }
}
?>