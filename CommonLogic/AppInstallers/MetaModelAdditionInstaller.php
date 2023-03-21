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
    
    private $appUid = null;
    
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
        // If the app is not installed yet, we don't know it's UID,
        // but we also don't need it for this case - so just let it
        // be NULL.
        if ($this->appInstalled === null) {
            try {
                $this->appUid = $this->getApp()->getUid();
                $this->appInstalled = true;
            } catch (AppNotFoundError $e) {
                $this->appInstalled = false;
            }
        }
        
        $additionalSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objectSelector);
        $additionalSheet->getFilters()->addConditionFromString($appRelationAttribute, $this->appUid, ComparatorDataType::EQUALS);
        $additionalSheet->getSorters()->addFromString($sorterAttribute, SortingDirectionsDataType::ASC);
        foreach ($additionalSheet->getMetaObject()->getAttributeGroup('~WRITABLE')->getAttributes() as $attr) {
            if (in_array($attr->getAlias(), $excludeAttributeAliases)){
                continue;
            }
            $additionalSheet->getColumns()->addFromExpression($attr->getAlias());
        }
        return $additionalSheet;
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