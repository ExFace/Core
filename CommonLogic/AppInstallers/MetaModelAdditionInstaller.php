<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\InstallerContainerInterface;
use exface\Core\Events\Installer\OnAppInstallEvent;
use exface\Core\Events\Installer\OnBeforeAppUninstallEvent;
use exface\Core\Events\Installer\OnAppBackupEvent;

/**
 * @deprecated use DataInstaller instead!
 * 
 * Allows to add additional data sheets to the MetaModelInstaller.
 * 
 * @author andrej.kabachnik
 *
 */
class MetaModelAdditionInstaller extends AbstractAppInstaller
{
    private $dataInstaller = null;
    
    public function __construct(SelectorInterface $selectorToInstall, InstallerContainerInterface $installerContainer, string $subfolder)
    {
        parent::__construct($selectorToInstall);
        $this->dataInstaller = new DataInstaller($selectorToInstall, MetaModelInstaller::FOLDER_NAME_MODEL . DIRECTORY_SEPARATOR . $subfolder);
        $this->dataInstaller->setFilenameIndexStart(1);
        
        $this->getWorkbench()->eventManager()->addListener(OnAppInstallEvent::getEventName(), [$this, 'handleInstall']);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeAppUninstallEvent::getEventName(), [$this, 'handleUninstall']);
        $this->getWorkbench()->eventManager()->addListener(OnAppBackupEvent::getEventName(), [$this, 'handleBackup']);
    }
    
    /**
     * Add an object to be exported with the app model replacing all rows on a target system when deploying
     *
     * @param string $objectSelector
     * @param string $sorterAttribute
     * @param string $appRelationAttribute
     * @param string[] $excludeAttributeAliases
     * @return MetaModelAdditionInstaller
     */
    public function addDataToReplace(string $objectSelector, string $sorterAttribute, string $appRelationAttribute, array $excludeAttributeAliases = []) : MetaModelAdditionInstaller
    {
        $this->dataInstaller->addDataToReplace($objectSelector, $sorterAttribute, $appRelationAttribute, $excludeAttributeAliases);
        return $this;
    }
    
    /**
     * Add an object to be exported with the app model replacing only rows with matching UIDs on a target system when deploying
     * 
     * @param string $objectSelector
     * @param string $sorterAttribute
     * @param string $appRelationAttribute
     * @param string[] $excludeAttributeAliases
     * @return MetaModelAdditionInstaller
     */
    public function addDataToMerge(string $objectSelector, string $sorterAttribute, string $appRelationAttribute = null, array $excludeAttributeAliases = []) : MetaModelAdditionInstaller
    {
        $this->dataInstaller->addDataToMerge($objectSelector, $sorterAttribute, $appRelationAttribute, $excludeAttributeAliases);
        return $this;
    }
    
    /**
     *
     * @param OnAppInstallEvent $event
     */
    public function handleInstall(OnAppInstallEvent $event)
    {
        if ($event->getAppSelector() !== $this->getSelectorInstalling()) {
            return false;
        }
        
        $event->addPostprocessor($this->dataInstaller->install($event->getSourcePath()));
    }
    
    /**
     *
     * @param OnBeforeAppUninstallEvent $event
     */
    public function handleUninstall(OnBeforeAppUninstallEvent $event)
    {
        if ($event->getAppSelector() !== $this->getSelectorInstalling()) {
            return false;
        }
        
        $this->dataInstaller->uninstall();
    }
    
    /**
     *
     * @param OnAppBackupEvent $event
     */
    public function handleBackup(OnAppBackupEvent $event)
    {
        if ($event->getAppSelector() !== $this->getSelectorInstalling()) {
            return false;
        }
        
        $event->addPostprocessor($this->dataInstaller->backup($event->getDestinationPath()));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::backup()
     */
    public function backup(string $absolute_path): \Iterator
    {
        yield from [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::uninstall()
     */
    public function uninstall(): \Iterator
    {
        yield from [];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\InstallerInterface::install()
     */
    public function install(string $source_absolute_path): \Iterator
    {
        yield from [];
    }
}