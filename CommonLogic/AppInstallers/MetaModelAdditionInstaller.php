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
    
    private $appInstalled = null;
    
    private $dataSheets = [];
    
    public function __construct(SelectorInterface $selectorToInstall, InstallerContainerInterface $installerContainer)
    {
        parent::__construct($selectorToInstall);
        $this->modelInstaller = $this->findModelInstaller($installerContainer);
        if ($this->modelInstaller === null) {
            throw new RuntimeException('Cannot initialize MetaModelAdditionInstaller: no MetaModelInstaller found!');
        }
    }
    
    public function addModelDataSheet(string $subfolder, DataSheetInterface $sheetToExport, string $lastUpdateAttributeAlias = null) : MetaModelAdditionInstaller
    {
        $this->modelInstaller->addModelDataSheet($subfolder, $sheetToExport, $lastUpdateAttributeAlias);
        return $this;
    }
    
    /**
     * 
     * @param string $subfolder
     * @param string $objectSelector
     * @param string $appRelationAttribute
     * @param string $sorterAttribute
     * @return DataSheetInterface
     */
    protected function createModelDataSheet(string $objectSelector, string $appRelationAttribute, string $sorterAttribute, array $excludeAttributeAliases = []) : DataSheetInterface
    {
        $cacheKey = $objectSelector . '::' . $appRelationAttribute . '::' . $sorterAttribute . '::' . implode(',', $excludeAttributeAliases);
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
            // when uninstalling an app, that is broken (this actually happened!)
            // If we know the UID at this moment, cache the sheet in case we are uninstalling and
            // we will need the sheet again after its model was removed.
            // If we don't konw the UID, do not cache the sheet - maybe the UID will be already
            // there next time (e.g. if we need the sheet after the app was installed)
            if ($appUid !== null) {
                $ds->getFilters()->addConditionFromString($appRelationAttribute, $appUid, ComparatorDataType::EQUALS);
                $this->dataSheets[$cacheKey] = $ds;
            } else {
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