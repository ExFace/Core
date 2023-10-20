<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\InstallerContainerInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\AppNotFoundError;
use exface\Core\Factories\DataSheetFactory;

/**
 * Allows to add additional data sheets to the MetaModelInstaller.
 * 
 * @author andrej.kabachnik
 *
 */
class MetaModelAdditionInstaller extends AbstractAppInstaller
{
    private $modelInstaller = null;
    
    private $dataSheets = [];
    
    private $subfolder = null;
    
    public function __construct(SelectorInterface $selectorToInstall, InstallerContainerInterface $installerContainer, string $subfolder)
    {
        parent::__construct($selectorToInstall);
        $this->modelInstaller = $this->findModelInstaller($installerContainer);
        if ($this->modelInstaller === null) {
            throw new RuntimeException('Cannot initialize MetaModelAdditionInstaller: no MetaModelInstaller found!');
        }
        if ($subfolder === '') {
            throw new RuntimeException('Cannot initialize MetaModelAdditionInstaller: empty subfolder defined!');
        }
        $this->subfolder = $subfolder;
    }
    
    /**
     * Add a custom data sheet to be exported with the app
     * 
     * @param string $subfolder
     * @param DataSheetInterface $sheetToExport
     * @param string $lastUpdateAttributeAlias
     * @return MetaModelAdditionInstaller
     */
    public function addModelDataSheet(string $subfolder, DataSheetInterface $sheetToExport, string $lastUpdateAttributeAlias = null) : MetaModelAdditionInstaller
    {
        $this->modelInstaller->addModelDataSheet($subfolder, $sheetToExport, $lastUpdateAttributeAlias);
        return $this;
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
        $sheet = $this->createModelDataSheet($objectSelector, $sorterAttribute, $appRelationAttribute, $excludeAttributeAliases);
        return $this->addModelDataSheet($this->subfolder, $sheet, $sorterAttribute);
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
        $sheet = $this->createModelDataSheet($objectSelector, $sorterAttribute, $appRelationAttribute, $excludeAttributeAliases);
        return $this->addModelDataSheet($this->subfolder, $sheet, $sorterAttribute);
    }
    
    /**
     * 
     * @param string $objectSelector
     * @param string $sorterAttribute
     * @param string $appRelationAttribute
     * @param string[] $excludeAttributeAliases
     * @return DataSheetInterface
     */
    protected function createModelDataSheet(string $objectSelector, string $sorterAttribute, string $appRelationAttribute = null, array $excludeAttributeAliases = []) : DataSheetInterface
    {
        $cacheKey = $objectSelector . '::' . ($appRelationAttribute ?? '') . '::' . $sorterAttribute . '::' . implode(',', $excludeAttributeAliases);
        if (null === $ds = $this->dataSheets[$cacheKey] ?? null) {
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objectSelector);
            $ds->getSorters()->addFromString($sorterAttribute, SortingDirectionsDataType::ASC);
            foreach ($ds->getMetaObject()->getAttributeGroup('~WRITABLE')->getAttributes() as $attr) {
                if (in_array($attr->getAlias(), $excludeAttributeAliases)){
                    continue;
                }
                $ds->getColumns()->addFromExpression($attr->getAlias());
            }
            
            try {
                $appUid = $this->getApp()->getUid();
            } catch (AppNotFoundError $e) {
                $appUid = null;
            }
            
            // It is very important to filter over app UID - otherwise we might uninstall EVERYTHING
            // when uninstalling an app, that is broken (this actually happened!).
            // Also make sure to cache the sheet in case we are uninstalling and
            // we will need the sheet again after its model was removed.
            switch (true) {
                // If there is not app relation, don't filter (nothing to filter over), but cache the sheet
                case $appRelationAttribute === null:
                    $this->dataSheets[$cacheKey] = $ds;
                    break;
                // If we know the UID at this moment, add a filter over the relation to the app
                case $appUid !== null:
                    $ds->getFilters()->addConditionFromString($appRelationAttribute, $appUid, ComparatorDataType::EQUALS);
                    $this->dataSheets[$cacheKey] = $ds;
                    break;
                // If we don't konw the UID, do not cache the sheet - maybe the UID will be already
                // there next time (e.g. if we need the sheet after the app was installed)
                default:
                    // If we do not have an app UID, make sure the filter NEVER matches anything, so the
                    // installer will not have any effect!
                    $ds->getFilters()->addConditionFromString($appRelationAttribute, '0x0', ComparatorDataType::EQUALS);
            }
        }
        
        return $ds->copy();
    }
    
    /**
     * 
     * @param InstallerContainerInterface $container
     * @return MetaModelInstaller|NULL
     */
    protected function findModelInstaller(InstallerContainerInterface $container) : ?MetaModelInstaller
    {
        $found = null;
        foreach ($container->getInstallers() as $installer) {
            if ($installer instanceof MetaModelInstaller) {
                $found = $installer;
                break;
            }
            if ($installer instanceof InstallerContainerInterface) {
                if ($found = $this->findModelInstaller($installer)) {
                    break;
                }
            }
        }
        return $found;
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