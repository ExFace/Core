<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\DataTypes\RelationDataType;
use exface\Core\DataTypes\RelationTypeDataType;

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
    
    public function getPropertyValueRecursive(UxonObject $uxon, array $path, string $propertyName, string $rootValue = '')
    {
        $value = $rootValue; 
        $prop = array_shift($path);
        
        if (is_numeric($prop) === false) {
            foreach ($uxon as $key => $val) {
                if (strcasecmp($key, $propertyName) === 0) {
                    $value = $val;
                }
            }
            
            if (count($path) > 1) {
                return $this->getPropertyValueRecursive($uxon->getProperty($prop), $path, $propertyName, $value);
            }
        }
        
        return $value;
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
    
    public function getValidValues(UxonObject $uxon, array $path, string $search = null) : array
    {
        $options = [];
        $prop = end($path);
        
        switch (mb_strtolower($prop)) {
            case 'object_alias':
                $options = $this->getObjectAliases($search);
                break;
            case 'attribute_alias':
                try {
                    $object = $this->getMetaObject($uxon, $path);
                    $options = $this->getAttributeAliases($object, $search);
                } catch (MetaObjectNotFoundError $e) {
                    $options = [];
                }
                break;
            default:
                $entityClass = $this->getEntityClass($uxon, $path);
                $propertyTypes = $this->getPropertyTypes($entityClass, $prop);
                $firstType = $propertyTypes[0];
                switch (true) {
                    case $this->isPropertyTypeEnum($firstType) === true:
                        $options = explode(',', trim($firstType, "[]"));
                        break;
                    case strcasecmp($firstType, 'boolean') === 0:
                        $options = ['true', 'false'];
                }  
        }
        
        return $options;
    }
    
    public function getMetaObject(UxonObject $uxon, array $path, MetaObjectInterface $rootObject = null) : MetaObjectInterface
    {
        $objectAlias = $this->getPropertyValueRecursive($uxon, $path, 'object_alias', ($rootObject !== null ? $rootObject->getAliasWithNamespace() : ''));
        if ($objectAlias === '' && $rootObject !== null) {
            return $rootObject;
        }
        return $this->getWorkbench()->model()->getObject($objectAlias);
    }
    
    protected function getObjectAliases(string $search = null) : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.OBJECT');
        $ds->getColumns()->addMultiple(['ALIAS', 'APP__ALIAS']);
        if ($search !== null) {
            $parts = explode('.', $search);
            if (count($parts) === 1) {
                return [];
            } else {
                $alias = $parts[2];
                $ds->addFilterFromString('APP__ALIAS', $parts[0] . '.' . $parts[1]);
            }
            $ds->addFilterFromString('ALIAS', $alias);
        }
        $ds->dataRead();
        
        $values = [];
        foreach ($ds->getRows() as $row) {
            $values[] = $row['APP__ALIAS'] . '.' . $row['ALIAS'];
        }
        
        sort($values);
        
        return $values;
    }
    
    protected function getAttributeAliases(MetaObjectInterface $object, string $search = null) : array
    {
        $rels = RelationPath::relationPathParse($search);
        $search = array_pop($rels);
        $relPath = null;
        if (! empty($rels)) {
            $relPath = implode(RelationPath::RELATION_SEPARATOR, $rels);
            $object = $object->getRelatedObject($relPath);
        }
        
        $values = [];
        foreach ($object->getAttributes() as $attr) {
            $values[] = ($relPath ? $relPath . RelationPath::RELATION_SEPARATOR : '') . $attr->getAlias();
        }
        // Reverse relations are not attributes, so we need to add them here manually
        foreach ($object->getRelations(RelationTypeDataType::REVERSE) as $rel) {
            $values[] = ($relPath ? $relPath . RelationPath::RELATION_SEPARATOR : '') . $rel->getAliasWithModifier();
        }
        
        sort($values);
        
        return $values;
    }
    
    protected function isPropertyTypeEnum(string $type) : bool
    {
        return substr($type, 0, 1) === '[' && substr($type, -1) === ']';
    }
}