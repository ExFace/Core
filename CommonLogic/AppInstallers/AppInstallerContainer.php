<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\AppInstallerInterface;
use exface\Core\Interfaces\InstallerInterface;
use exface\Core\Interfaces\InstallerContainerInterface;
use exface\Core\Events\Installer\OnBeforeInstallEvent;
use exface\Core\Events\Installer\OnInstallEvent;
use exface\Core\Events\Installer\OnUninstallEvent;
use exface\Core\Events\Installer\OnBeforeUninstallEvent;
use exface\Core\Events\Installer\OnBeforeBackupEvent;
use exface\Core\Events\Installer\OnBackupEvent;

/**
 * Contains a stack of installers and executes them one-after-another.
 * 
 * The uninstall operation is performed in reverse order.
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
     * 
     * @triggers \exface\Core\Events\Installer\OnBeforeInstallEvent
     * @triggers \exface\Core\Events\Installer\OnInstallEvent
     * 
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public final function install(string $source_absolute_path) : \Iterator
    {
        // Disable mutations
        $mutationsEnabled = $this->getWorkbench()->getConfig()->getOption('MUTATIONS.ENABLED');
        if ($mutationsEnabled === true) {
            $this->getWorkbench()->getConfig()->setOption('MUTATIONS.ENABLED', false);
        }

        $eventMgr = $this->getWorkbench()->eventManager();
        foreach ($this->getInstallers() as $installer) {
            $eventMgr->dispatch(new OnBeforeInstallEvent($installer, $source_absolute_path));
            yield from $installer->install($source_absolute_path);
            $eventMgr->dispatch(new OnInstallEvent($installer, $source_absolute_path));
        }

        // Re-enable mutations
        if ($mutationsEnabled === true) {
            $this->getWorkbench()->getConfig()->setOption('MUTATIONS.ENABLED', true);
        }
        
        // Update model install timestamp to make sure other code can update caches, etc.
        // This is particularly important for AJAX facades, that will append a cash buster
        // string to URLs for included files to control browser caching
        // @see \exface\Core\Facades\AbstractAjaxFacade::getFileVersionHash()
        $this->getWorkbench()->getContext()->getScopeInstallation()->setVariable('last_metamodel_install', DateTimeDataType::now());
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
     * {@inheritdoc}
     * 
     * @triggers \exface\Core\Events\Installer\OnBeforeBackupEvent
     * @triggers \exface\Core\Events\Installer\OnBackupEvent
     * 
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public final function backup(string $destination_absolute_path) : \Iterator
    {
        // Disable mutations
        $mutationsEnabled = $this->getWorkbench()->getConfig()->getOption('MUTATIONS.ENABLED');
        if ($mutationsEnabled === true) {
            $this->getWorkbench()->getConfig()->setOption('MUTATIONS.ENABLED', false);
        }

        $fm = $this->getWorkbench()->filemanager();
        $appSelector = $this->getSelectorInstalling();
        $appPath = $fm->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $appSelector->getFolderRelativePath();
        $this->getWorkbench()->filemanager()->pathConstruct($destination_absolute_path);
        $fm->copyDir($appPath, $destination_absolute_path);
        
        $eventMgr = $this->getWorkbench()->eventManager();
        foreach ($this->getInstallers() as $installer) {
            $eventMgr->dispatch(new OnBeforeBackupEvent($installer, $destination_absolute_path));
            yield from $installer->backup($destination_absolute_path);
            $eventMgr->dispatch(new OnBackupEvent($installer, $destination_absolute_path));
        }

        // Re-enable mutations
        if ($mutationsEnabled === true) {
            $this->getWorkbench()->getConfig()->setOption('MUTATIONS.ENABLED', true);
        }
    }

    /**
     * Makes every installer uninstall iterating in reverse order (last installer uninstalling first)
     * 
     * @triggers \exface\Core\Events\Installer\OnBeforeUninstallEvent
     * @triggers \exface\Core\Events\Installer\OnUninstallEvent
     * 
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public final function uninstall() : \Iterator
    {
        // TODO disable mutations here too???
        $eventMgr = $this->getWorkbench()->eventManager();
        foreach (array_reverse($this->getInstallers()) as $installer) {
            $eventMgr->dispatch(new OnBeforeUninstallEvent($installer));
            yield from $installer->uninstall();
            $eventMgr->dispatch(new OnUninstallEvent($installer));
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerContainerInterface::addInstaller()
     */
    public function addInstaller(InstallerInterface $installer, $insertAtBeinning = false) : InstallerContainerInterface
    {
        if ($insertAtBeinning) {
            array_unshift($this->installers, $installer);
        } else {
            $this->installers[] = $installer;
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerContainerInterface::getInstallers()
     */
    public function getInstallers() : array
    {
        return $this->installers;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerContainerInterface::extract()
     */
    public function extract(callable $filterCallback) : InstallerContainerInterface
    {
        $container = new self($this->getSelectorInstalling());
        foreach (array_filter($this->installers, $filterCallback) as $installer) {
            $container->addInstaller($installer);   
        }
        return $container;
    }
}