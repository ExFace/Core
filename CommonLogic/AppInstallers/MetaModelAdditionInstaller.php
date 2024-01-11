<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\InstallerContainerInterface;
use exface\Core\Events\Installer\OnInstallEvent;
use exface\Core\Events\Installer\OnBeforeUninstallEvent;
use exface\Core\Events\Installer\OnBackupEvent;
use exface\Core\Interfaces\SelectorInstallerInterface;
use exface\Core\Interfaces\Events\InstallerEventInterface;

/**
 * Allows to add additional data sheets to the MetaModelInstaller.
 * 
 * @author andrej.kabachnik
 *
 */
class MetaModelAdditionInstaller extends AbstractAppInstaller
{
    private $dataInstaller = null;
    
    private $dataSheets = [];
    
    private $subfolder = null;
    
    public function __construct(SelectorInterface $selectorToInstall, InstallerContainerInterface $installerContainer, string $subfolder)
    {
        parent::__construct($selectorToInstall);
        $this->dataInstaller = new DataInstaller($selectorToInstall, $subfolder);
        
        $this->getWorkbench()->eventManager()->addListener(OnInstallEvent::getEventName(), [$this, 'handleInstall']);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUninstallEvent::getEventName(), [$this, 'handleUninstall']);
        $this->getWorkbench()->eventManager()->addListener(OnBackupEvent::getEventName(), [$this, 'handleBackup']);
        
        $this->subfolder = $subfolder;
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
     * @param InstallerEventInterface $event
     * @return bool
     */
    protected function isModelInstaller(InstallerEventInterface $event) : bool
    {
        $installer = $event->getInstaller();
        if (! ($installer instanceof MetaModelInstaller)) {
            return false;
        }
        if (! ($installer instanceof SelectorInstallerInterface) || $installer->getSelectorInstalling() !== $this->getSelectorInstalling()) {
            return false;
        }
        return true;
    }
    
    /**
     *
     * @param OnInstallEvent $event
     */
    public function handleInstall(OnInstallEvent $event)
    {
        if (! $this->isModelInstaller($event)) {
            return;
        }
        
        $event->addPostprocessor($this->dataInstaller->install($event->getSourcePath()));
    }
    
    /**
     *
     * @param OnBeforeUninstallEvent $event
     */
    public function handleUninstall(OnBeforeUninstallEvent $event)
    {
        if (! $this->isModelInstaller($event)) {
            return;
        }
        
        $this->dataInstaller->uninstall();
    }
    
    /**
     *
     * @param OnBackupEvent $event
     */
    public function handleBackup(OnBackupEvent $event)
    {
        if (! $this->isModelInstaller($event)) {
            return;
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