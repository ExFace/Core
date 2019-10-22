<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\AppInstallerInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Interfaces\InstallerContainerInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class AppInstallerContainer extends AbstractAppInstaller implements AppInstallerInterface, InstallerContainerInterface
{

    private $installers = array();

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public final function install($source_absolute_path)
    {
        foreach ($this->getInstallers() as $installer) {
            $res = $installer->install($source_absolute_path);
            if ($res instanceof \Traversable) {
                yield from $res;
            } else {
                yield rtrim($res, " .\n\r") . PHP_EOL;
            }
        }
    }

    /**
     * Creates the given backup path if neccessary, copies the entire app folder
     * there and runs the backup-procedures of all installers in the container
     * afterwards.
     * 
     * Thus, the backup folder will contain the current state of the app including
     * it's meta model, pages, etc. at the time of backup.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public final function backup($destination_absolute_path)
    {
        $fm = $this->getWorkbench()->filemanager();
        $appSelector = $this->getSelectorInstalling();
        $appPath = $fm->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $appSelector->getFolderRelativePath();
        $result = '';
        $this->getWorkbench()->filemanager()->pathConstruct($destination_absolute_path);
        $fm->copyDir($appPath, $destination_absolute_path);
        
        foreach ($this->getInstallers() as $installer) {
            $result .= $installer->backup($destination_absolute_path);
        }
        
        $result .= '';
        return $result;
    }

    public final function uninstall()
    {}

    public function addInstaller(InstallerInterface $installer, $insert_at_beinning = false)
    {
        if ($insert_at_beinning) {
            array_unshift($this->installers, $installer);
        } else {
            $this->installers[] = $installer;
        }
        return $this;
    }

    public function getInstallers()
    {
        return $this->installers;
    }
}