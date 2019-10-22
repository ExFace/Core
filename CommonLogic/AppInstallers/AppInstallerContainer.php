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
    public final function install(string $source_absolute_path) : \Iterator
    {
        foreach ($this->getInstallers() as $installer) {
            $res = $installer->install($source_absolute_path);
            yield from $res;
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
    public final function backup(string $destination_absolute_path) : \Iterator
    {
        $fm = $this->getWorkbench()->filemanager();
        $appSelector = $this->getSelectorInstalling();
        $appPath = $fm->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $appSelector->getFolderRelativePath();
        $this->getWorkbench()->filemanager()->pathConstruct($destination_absolute_path);
        $fm->copyDir($appPath, $destination_absolute_path);
        
        foreach ($this->getInstallers() as $installer) {
            yield from $installer->backup($destination_absolute_path);
        }
    }

    public final function uninstall() : \Iterator
    {
        return new \EmptyIterator();
    }

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