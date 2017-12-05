<?php
namespace exface\Core;

use exface\Core\CommonLogic\AppInstallers\AbstractAppInstaller;

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
        return $model_source_installer->install($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $model_source_installer->getNameResolver()->getClassDirectory());
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
        return $model_source_installer->update($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $model_source_installer->getNameResolver()->getClassDirectory());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall()
    {
        return 'Uninstall not implemented for installer "' . $this->getNameResolver()->getAliasWithNamespace() . '"!';
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup($destination_absolute_path)
    {
        return 'Backup not implemented for' . $this->getNameResolver()->getAliasWithNamespace() . '!';
    }
}
?>