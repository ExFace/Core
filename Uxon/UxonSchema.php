<?php
namespace exface\Core\Uxon;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * This class provides varios tools to analyse and validate a generic UXON object.
 * 
 * The generic UXON object supports the following value types:
 * 
 * - string
 * - number
 * - integer
 * - date
 * - datetime
 * - object
 * - array
 * - icon
 * - color
 * - uri
 * - metamodel:expression
 * - metamodel:object
 * - metamodel:attribute
 * - metamodel:attribute_group
 * - metamodel:relation
 * - metamodel:action
 * - metamodel:page
 * - metamodel:comparator
 * - metamodel:connection
 * - metamodel:formula
 * - metamodel:widget_link
 * - metamodel:event
 * - metamodel:data_source
 * - uxon:path - where path is a JSONpath relative to the current field
 * - [val1,val2] - enumeration of commma-separated values (in square brackets)
 * 
 * There are dedicated schema-classes for some UXON schemas:
 * 
 * @see WidgetSchema
 * @see ActionSchema
 * @see DatatypeSchema
 * @see BehaviorSchema
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonSchema implements UxonSchemaInterface
{    
    private $prototypePropCache = [];
    
    private $schemaCache = [];
    
    private $parentSchema = null;
    
    private $workbench;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench, UxonSchema $parentSchema = null)
    {
        $this->parentSchema = $parentSchema;
        $this->workbench = $workbench;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UxonSchemaInterface::getPrototypeClass()
     */
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass) : string
    {
        if (count($path) > 1) {
            $prop = array_shift($path);
            
            if (is_numeric($prop) === false) {
                $propType = $this->getPropertyTypes($rootPrototypeClass, $prop)[0];
                if (substr($propType, 0, 1) === '\\') {
                    $class = $propType;
                    $class = str_replace('[]', '', $class);
                } else {
                    $class = $rootPrototypeClass;
                }
            } else {
                $class = $rootPrototypeClass;
            }
            
            $schema = $class === $rootPrototypeClass ? $this : $this->getSchemaForClass($class);
            
            return $schema->getPrototypeClass($uxon->getProperty($prop), $path, $class);
        }
        
        return $rootPrototypeClass;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see UxonSchemaInterface::getPropertyValueRecursive()
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
     * 
     * {@inheritdoc}
     * @see UxonSchemaInterface::getProperties()
     */
    public function getProperties(string $prototypeClass) : array
    {
        if ($col = $this->getPropertiesSheet($prototypeClass)->getColumns()->get('PROPERTY')) {
            return $col->getValues(false);
        }
            
        return [];
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UxonSchemaInterface::getPropertiesTemplates()
     */
    public function getPropertiesTemplates(string $prototypeClass) : array
    {
        $tpls = [];
        $ds = $this->getPropertiesSheet($prototypeClass);
        if ($col = $ds->getColumns()->get('TEMPLATE')) {
            $propertyCol = $ds->getColumns()->get('PROPERTY');
            foreach ($col->getValues() as $r => $tpl) {
                if ($tpl !== null) {
                    $tpls[$propertyCol->getCellValue($r)] = $tpl;
                }
            }
        }
        
        return $tpls;
    }
    
    /**
     * 
     * @param string $prototypeClass
     * @return DataSheetInterface
     */
    protected function getPropertiesSheet(string $prototypeClass) : DataSheetInterface
    {
        if ($cache = $this->prototypePropCache[$prototypeClass]) {
            return $cache;
        }
        
        if ($cache = $this->getCache($prototypeClass, 'properties')) {
            return DataSheetFactory::createFromUxon($this->getWorkbench(), $cache);
        }
        
        $filepathRelative = $this->getFilenameForEntity($prototypeClass);
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_PROPERTY_ANNOTATION');
        $ds->getColumns()->addMultiple(['PROPERTY', 'TYPE', 'TEMPLATE', 'DEFAULT']);
        $ds->addFilterFromString('FILE', $filepathRelative);
        try {
            $ds->dataRead();
        } catch (\Throwable $e) {
            // TODO
        }
        $this->prototypePropCache[$prototypeClass] = $ds;
        $this->setCache($prototypeClass, 'properties', $ds->exportUxonObject());
        
        return $ds;
    }
    
    protected function getCache(string $prototypeClass, string $key)
    {
        return $this->getWorkbench()->getCache()->getPool('uxon.schema')->get($key . '.' . str_replace("\\", '.', $prototypeClass));
    }
    
    protected function setCache(string $prototypeClass, string $key, $data) : UxonSchema
    {
        $this->getWorkbench()->getCache()->getPool('uxon.schema')->set($key . '.' . str_replace("\\", '.', $prototypeClass), $data);
        return $this;
    }

    /**
     * 
     * @param string $prototypeClass
     * @return string
     */
    protected function getFilenameForEntity(string $prototypeClass) : string
    {
        $path = str_replace('\\', '/', $prototypeClass);
        return ltrim($path, "/") . '.php';
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see UxonSchemaInterface::getPropertyTypes()
     */
    public function getPropertyTypes(string $prototypeClass, string $property) : array
    {
        foreach ($this->getPropertiesSheet($prototypeClass)->getRows() as $row) {
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
     * 
     * {@inheritdoc}
     * @see UxonSchemaInterface::getValidValues()
     */
    public function getValidValues(UxonObject $uxon, array $path, string $search = null, string $rootPrototypeClass = null, MetaObjectInterface $rootObject = null) : array
    {
        $options = [];
        
        $prop = mb_strtolower(end($path));
        if (true === is_numeric($prop)) {
            // If we are in an array, use the data from the parent property (= the array)
            // for every item within the array.
            $prop = mb_strtolower($path[(count($path)-2)]);
            $prototypeClass = $rootPrototypeClass !== null ? $this->getPrototypeClass($uxon, $path, $rootPrototypeClass) : $this->getPrototypeClass($uxon, $path);
            $propertyTypes = $this->getPropertyTypes($prototypeClass, $prop);
            $firstType = trim($propertyTypes[0]);
            $firstType = rtrim($firstType, "[]");
        } else {
            // In all other cases, try to find something for the top-most property in the path
            $prototypeClass = $rootPrototypeClass !== null ? $this->getPrototypeClass($uxon, $path, $rootPrototypeClass) : $this->getPrototypeClass($uxon, $path);
            $propertyTypes = $this->getPropertyTypes($prototypeClass, $prop);
            $firstType = trim($propertyTypes[0]);
        }
        
        switch (true) {
            case $this->isPropertyTypeEnum($firstType) === true:
                $options = explode(',', trim($firstType, "[]"));
                break;
            case strcasecmp($firstType, 'boolean') === 0:
                $options = ['true', 'false'];
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
            case strcasecmp($firstType, 'metamodel:page') === 0:
                $options = $this->getMetamodelPageAliases($search);
                break;
            case strcasecmp($firstType, 'metamodel:comparator') === 0:
                $options = $this->getMetamodelComparators($search);
                break;
            case strcasecmp($firstType, 'metamodel:attribute') === 0:
            case strcasecmp($firstType, 'metamodel:relation') === 0:
                try {
                    $object = $this->getMetaObject($uxon, $path, $rootObject);
                    if (strcasecmp($firstType, 'metamodel:attribute') === 0) {
                        $options = $this->getMetamodelAttributeAliases($object, $search);
                    } else {
                        $options = $this->getMetamodelRelationAliases($object, $search);
                    }
                } catch (MetaObjectNotFoundError $e) {
                    $options = [];
                }
                break;
            case strcasecmp($firstType, 'metamodel:expression') === 0:
                try {
                    $object = $this->getMetaObject($uxon, $path, $rootObject);
                    $ex = ExpressionFactory::createFromString($this->getWorkbench(), $search, $object);
                    if ($ex->isFormula() === true) {
                        // TODO
                    } elseif ($ex->isReference() === true) {
                        // TODO
                    } elseif ($ex->isNumber()) {
                        // Do nothing - a number is simply a number
                    } else {
                        // If the expression is neither of the above, try to interpret it as an attribute
                        $options = $this->getMetamodelAttributeAliases($object, $search);
                    }
                } catch (MetaObjectNotFoundError $e) {
                    $options = [];
                }
                break;
            
        } 
        
        return $options;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UxonSchemaInterface::getMetaObject()
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
        $rels = $search !== null ? RelationPath::relationPathParse($search) : [];
        $search = array_pop($rels) ?? '';
        $relPath = null;
        if (! empty($rels)) {
            $relPath = implode(RelationPath::RELATION_SEPARATOR, $rels);
            $object = $object->getRelatedObject($relPath);
        }
        
        $values = [];
        $value_relations = [];
        foreach ($object->getAttributes() as $attr) {
            $alias = ($relPath ? $relPath . RelationPath::RELATION_SEPARATOR : '') . $attr->getAlias();
            $values[] = $alias;
            if ($attr->isRelation() === true) {
                // Remember forward-relations to append them later (after alphabetical sorting)
                $value_relations[] = $alias . RelationPath::RELATION_SEPARATOR;
            }
        }
        // Reverse relations are not attributes, so we need to add them here manually
        foreach ($object->getRelations(RelationTypeDataType::REVERSE) as $rel) {
            $values[] = ($relPath ? $relPath . RelationPath::RELATION_SEPARATOR : '') . $rel->getAliasWithModifier() . RelationPath::RELATION_SEPARATOR;
        }
        
        // Sort attributes and reverse relations alphabetically.
        sort($values);
        
        // Now insert forward relations _before_ the corresponding attribute: relation attributes rarely
        // get used directly, but rather as parts of a relation path.
        foreach ($value_relations as $val) {
            $idx = array_search(rtrim($val, RelationPath::RELATION_SEPARATOR), $values);
            if ($idx !== false) {
                array_splice($values, $idx, 0, [$val]);
            } else {
                $values[] = $val;
            }
        }
        
        return $values;
    }
    
    protected function getMetamodelRelationAliases(MetaObjectInterface $object, string $search = null) : array
    {
        $attrAliases = $this->getMetamodelAttributeAliases($object, $search);
        $relAliases = [];
        $relSep = RelationPath::getRelationSeparator();
        foreach ($attrAliases as $alias) {
            if (true === StringDataType::endsWith($alias, $relSep)) {
                $relAliases[] = StringDataType::substringBefore($alias, $relSep);  
            } 
        }
        return $relAliases;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getMetamodelWidgetTypes() : array
    {
        if ($cache = $this->getCache('', 'widgetTypes')) {
            return $cache;
        }
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.WIDGET');
        $ds->getColumns()->addFromExpression('NAME');
        $ds->dataRead();
        $types = $ds->getColumns()->get('NAME')->getValues(false);
        
        $this->setCache('', 'widgetTypes', $types);
        
        return $types;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getMetamodelActionAliases() : array
    {
        if ($cache = $this->getCache('', 'actionAliases')) {
            return $cache;
        }
        
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
        $ds->getColumns()->addMultiple(['ALIAS', 'APP__ALIAS']);
        $ds->dataRead();
        foreach ($ds->getRows() as $row) {
            $options[] = $row['APP__ALIAS'] . $dot . $row['ALIAS'];
        }
        
        $this->setCache('', 'actionAliases', $options);
        
        return $options;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getMetamodelPageAliases() : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PAGE');
        $ds->getColumns()->addFromExpression('ALIAS');
        $ds->dataRead();
        return $ds->getColumns()->get('ALIAS')->getValues(false);
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getMetamodelComparators() : array
    {
        return array_values(ComparatorDataType::getValuesStatic());
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
    
    /**
     * 
     * @param string $prototypeClass
     * @return bool
     */
    protected function validatePrototypeClass(string $prototypeClass) : bool
    {
        return class_exists($prototypeClass);
    }
    
    /**
     * Returns the schema instance matching the given prototype class: e.g. widget schema for widgets, etc.
     * 
     * @param string $prototypeClass
     * @return UxonSchema
     */
    protected function getSchemaForClass(string $prototypeClass) : UxonSchema
    {
        $class = null;
        if (is_subclass_of($prototypeClass, iCanBeConvertedToUxon::class)) {
            $class = $prototypeClass::getUxonSchemaClass();
        } 
        
        if ($class === null || is_subclass_of($this, $class)) {
            return $this;
        }
        
        $cache = $this->schemaCache[$class];
        if ($cache !== null) {
            return $cache;
        } else {
            $schema = new $class($this->getWorkbench(), $this);
            $this->schemaCache[$class] = $schema;
        }
        
        return $schema;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see UxonSchemaInterface::hasParentSchema()
     */
    public function hasParentSchema() : bool
    {
        return $this->parentSchema !== null;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see UxonSchemaInterface::getParentSchema()
     */
    public function getParentSchema() : UxonSchemaInterface
    {
        return $this->parentSchema;
    }
}