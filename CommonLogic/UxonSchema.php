<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

/**
 * This class provides varios tools to analyse and validate a generic UXON object.
 * 
 * There are dedicated schema-classes for some UXON schemas:
 * 
 * @see UxonWidgetSchema
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonSchema implements WorkbenchDependantInterface
{    
    private $entityPropCache = [];
    
    private $workbench;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * Returns the entity class for a given path.
     * 
     * @param UxonObject $uxon
     * @param array $path
     * @param string $rootEntityClass
     * @return string
     */
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
    
    /**
     * Returns the value of an inheritable property from the point of view of the end of the given path.
     * 
     * This is usefull for common properties like `object_alias`, that get inherited from the parent 
     * entity automatically, but can be specified explicitly by the user.
     * 
     * @param UxonObject $uxon
     * @param array $path
     * @param string $propertyName
     * @param string $rootValue
     * @return mixed
     */
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
        }
        
        if (count($path) > 1) {
            return $this->getPropertyValueRecursive($uxon->getProperty($prop), $path, $propertyName, $value);
        }
        
        return $value;
    }
    
    /**
     * Returns an array with names of all properties of a given entity class.
     * 
     * @param string $entityClass
     * @return string[]
     */
    public function getProperties(string $entityClass) : array
    {
        if ($col = $this->getPropertiesSheet($entityClass)->getColumns()->get('PROPERTY')) {
            return $col->getValues(false);
        }
            
        return [];
    }
    
    /**
     * 
     * @param string $entityClass
     * @return DataSheetInterface
     */
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

    /**
     * 
     * @param string $entityClass
     * @return string
     */
    protected function getFilenameForEntity(string $entityClass) : string
    {
        $path = str_replace('\\', '/', $entityClass);
        return ltrim($path, "/") . '.php';
    }
    
    /**
     * Returns an array of UXON types valid for the given entity class property.
     * 
     * The result is an array, because a property may accept multiple types
     * (separated by a pipe (|) in the UXON annotations). The array elements
     * have the same order, as the types in the annotation.
     * 
     * @param string $entityClass
     * @param string $property
     * @return string[]
     */
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * Returns an array of valid values for properties with fixe values (or an empty array for non-enum properties).
     * 
     * @param UxonObject $uxon
     * @param array $path
     * @param string $search
     * @return string[]
     */
    public function getValidValues(UxonObject $uxon, array $path, string $search = null) : array
    {
        $options = [];
        $prop = mb_strtolower(end($path));
        
        $entityClass = $this->getEntityClass($uxon, $path);
        $propertyTypes = $this->getPropertyTypes($entityClass, $prop);
        $firstType = trim($propertyTypes[0]);
        switch (true) {
            case $this->isPropertyTypeEnum($firstType) === true:
                $options = explode(',', trim($firstType, "[]"));
                break;
            case strcasecmp($firstType, 'metamodel:widget') === 0:
                $options = $this->getMetamodelWidgetTypes();
                break;
            case strcasecmp($firstType, 'metamodel:object') === 0:
                $options = $this->getMetamodelObjectAliases($search);
                break;
            case strcasecmp($firstType, 'metamodel:action') === 0:
                $options = $this->getMetamodelActionAliases($search);
                break;
            case strcasecmp($firstType, 'metamodel:attribute') === 0:
                try {
                    $object = $this->getMetaObject($uxon, $path);
                    $options = $this->getMetamodelAttributeAliases($object, $search);
                } catch (MetaObjectNotFoundError $e) {
                    $options = [];
                }
                break;
            case strcasecmp($firstType, 'boolean') === 0:
                $options = ['true', 'false'];
                break;
        } 
        
        return $options;
    }
    
    /**
     * Returns the meta object for the entity at the end of the path.
     * 
     * @param UxonObject $uxon
     * @param array $path
     * @param MetaObjectInterface $rootObject
     * @return MetaObjectInterface
     */
    public function getMetaObject(UxonObject $uxon, array $path, MetaObjectInterface $rootObject = null) : MetaObjectInterface
    {
        $objectAlias = $this->getPropertyValueRecursive($uxon, $path, 'object_alias', ($rootObject !== null ? $rootObject->getAliasWithNamespace() : ''));
        if ($objectAlias === '' && $rootObject !== null) {
            return $rootObject;
        }
        return $this->getWorkbench()->model()->getObject($objectAlias);
    }
    
    /**
     * 
     * @param string $search
     * @return string[]
     */
    protected function getMetamodelObjectAliases(string $search = null) : array
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
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param string $search
     * @return string[]
     */
    protected function getMetamodelAttributeAliases(MetaObjectInterface $object, string $search = null) : array
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
            $alias = ($relPath ? $relPath . RelationPath::RELATION_SEPARATOR : '') . $attr->getAlias();
            $values[] = $alias;
            if ($attr->isRelation() === true) {
                $values[] = $alias . RelationPath::RELATION_SEPARATOR;
            }
        }
        // Reverse relations are not attributes, so we need to add them here manually
        foreach ($object->getRelations(RelationTypeDataType::REVERSE) as $rel) {
            $values[] = ($relPath ? $relPath . RelationPath::RELATION_SEPARATOR : '') . $rel->getAliasWithModifier() . RelationPath::RELATION_SEPARATOR;
        }
        
        sort($values);
        
        return $values;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getMetamodelWidgetTypes() : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.WIDGET');
        $ds->getColumns()->addFromExpression('NAME');
        $ds->dataRead();
        return $ds->getColumns()->get('NAME')->getValues(false);
    }
    
    /**
     *
     * @return string[]
     */
    protected function getMetamodelActionAliases() : array
    {
        $options = [];
        $dot = AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;
        
        // Prototypes
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.ACTION');
        $ds->getColumns()->addMultiple(['NAME', 'PATH_RELATIVE']);
        $ds->dataRead();
        foreach ($ds->getRows() as $row) {
            $namespace = str_replace(['/Actions', '/'], ['', $dot], $row['PATH_RELATIVE']);
            $options[] = $namespace . $dot . $row['NAME'];
        }
        
        // Action models
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.OBJECT_ACTION');
        $ds->getColumns()->addMultiple(['ALIAS', 'APP_ALIAS']);
        $ds->dataRead();
        foreach ($ds->getRows() as $row) {
            $options[] = $row['APP_ALIAS'] . $dot . $row['ALIAS'];
        }
        
        return $options;
    }
    
    /**
     * 
     * @param string $type
     * @return bool
     */
    protected function isPropertyTypeEnum(string $type) : bool
    {
        return substr($type, 0, 1) === '[' && substr($type, -1) === ']';
    }
    
    protected function validateEntityClass(string $entityClass) : bool
    {
        return class_exists($entityClass);
    }
}