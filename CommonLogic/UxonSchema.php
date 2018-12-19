<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class UxonSchema implements WorkbenchDependantInterface
{    
    private $entityPropCache = [];
    
    private $workbench;
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public function getEntityClass(UxonObject $uxon, array $path, string $rootEntityClass) : string
    {
        if (count($path) > 1) {
            $prop = array_shift($path);
            
            if (is_numeric($prop) === false) {
                $propType = $this->getPropertyTypes($rootEntityClass, $prop)[0];
                if (substr($propType, 0, 1) === '\\') {
                    $expectedEntityName = $propType;
                    $expectedEntityName = str_replace('[]', '', $expectedEntityName);
                } else {
                    $expectedEntityName = $rootEntityClass;
                }
            } else {
                $expectedEntityName = $rootEntityClass;
            }
            return $this->getEntityClass($uxon->getProperty($prop), $path, $expectedEntityName);
        }
        
        return $rootEntityClass;
    }
    
    public function getProperties(string $entityClass) : array
    {
        if ($col = $this->getPropertiesSheet($entityClass)->getColumns()->get('PROPERTY')) {
            return $col->getValues(false);
        }
            
        return [];
    }
    
    protected function getPropertiesSheet(string $entityClass) : DataSheetInterface
    {
        if ($cache = $this->entityPropCache[$entityClass]) {
            return $cache;
        }
        
        $filepathRelative = $this->getFilenameForEntity($entityClass);
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_PROPERTY_ANNOTATION');
        $ds->getColumns()->addMultiple(['PROPERTY', 'TYPE']);
        $ds->addFilterFromString('FILE', $filepathRelative);
        try {
            $ds->dataRead();
        } catch (\Throwable $e) {
            // TODO
        }
        $this->entityPropCache[$entityClass] = $ds;
        
        return $ds;
    }
    
    protected function getFilenameForEntity(string $entityClass) : string
    {
        $path = str_replace('\\', '/', $entityClass);
        return ltrim($path, "/") . '.php';
    }
    
    public function getPropertyTypes(string $entityClass, string $property) : array
    {
        foreach ($this->getPropertiesSheet($entityClass)->getRows() as $row) {
            if (strcasecmp($row['PROPERTY'], $property) === 0) {
                $type = $row['TYPE'];
                break;
            }
        }
        
        return explode('|', $type);
    }
    
    /**
     * 
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
}