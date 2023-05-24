<?php
namespace exface\Core\Uxon;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\UxonSchemaNameDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\WorkbenchCache;
use exface\Core\DataTypes\TimeZoneDataType;

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
 * - timezone
 * - metamodel:app
 * - metamodel:expression
 * - metamodel:object
 * - metamodel:attribute
 * - metamodel:attribute_group
 * - metamodel:relation
 * - metamodel:action
 * - metamodel:page
 * - metamodel:data_source
 * - metamodel:comparator
 * - metamodel:connection
 * - metamodel:datatype
 * - metamodel:formula
 * - metamodel:expression
 * - metamodel:widget_link
 * - metamodel:event
 * - metamodel:aggregator
 * - metamodel:context
 * - metamodel:role
 * - metamodel:username
 * - metamodel:communication_channel
 * - metamodel:facade (TODO)
 * - metamodel:<object_alias>:<attribute_alias> - values of the meta attribute
 * - uxon:path - where path is a JSONpath relative to the current field
 * - [val1,val2] - enumeration of commma-separated values (in square brackets)
 * - {keyType => valueType} - object with typed keys and values
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
    const SCHEMA_WIDGET = 'widget';
    const SCHEMA_ACTION = 'action';
    const SCHEMA_BEHAVIOR = 'behavior';
    const SCHEMA_DATATYPE = 'datatype';
    const SCHEMA_CONNECTION = 'connection';
    const SCHEMA_QUERYBUILDER = 'querybuilder';
    const SCHEMA_QUERYBUILDER_ATTRIBUTE = 'querybuilder_attribute';
    const SCHEMA_QUERYBUILDER_OBJECT = 'querybuilder_object';
    
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
    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : string
    {
        $rootPrototypeClass = $rootPrototypeClass ?? $this->getDefaultPrototypeClass();
        
        if ($rootPrototypeClass === '') {
            return $rootPrototypeClass;
        }
        
        if (count($path) > 0 && ($uxon->getProperty($path[0]) instanceof UxonObject)) {
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
    
    protected function getDefaultPrototypeClass() : string
    {
        return '';
    }
    
    /**
     * Recursively returning property content valu
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
        
        if (count($path) > 0) {
            // If the current property is an array, we need to step into one of the arrays
            // elements to get the next schema (because the array itself is still part of
            // the parent schema)
            if (is_numeric($path[0])) {
                $prop2 = array_shift($path);
                $nextStep = [$prop, $prop2];
                $nextUxon = $uxon->getProperty($prop)->getProperty($prop2);
                if (! ($nextUxon instanceof UxonObject)) {
                    return $value;
                }
            } else {
                $nextStep = [$prop];
                $nextUxon = $uxon->getProperty($prop);
            }
            $nextSchema = $this->getSchemaForClass($this->getPrototypeClass($uxon, $nextStep));
            return $nextSchema->getPropertyValueRecursive($nextUxon, $path, $propertyName, $value);
        }
        
        return $value;
    }
    
    /**
     * 
     * @param UxonObject $uxon
     * @param array $path
     * @return UxonObject
     */
    protected function getPathTargetUxon(UxonObject $uxon, array $path) : UxonObject
    {
        $level = $uxon;
        foreach ($path as $step) {
            $value = $level->getProperty($step);
            if ($value instanceof UxonObject) {
                $level = $value;
            }
        }
        return $level;
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
                    $tpls[$propertyCol->getCellValue($r)] = str_replace('\n', "\n", $tpl);
                }
            }
        }
        
        return $tpls;
    }
    
    /**
     * Returning properties sheet
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
        $ds->getColumns()->addMultiple([
            'PROPERTY', 
            'TYPE', 
            'TEMPLATE', 
            'DEFAULT',
            'REQUIRED',
            'TRANSLATABLE'
        ]);
        $ds->getFilters()->addConditionFromString('FILE', $filepathRelative);
        try {
            $ds->dataRead();
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            // TODO
        }
        $this->prototypePropCache[$prototypeClass] = $ds;
        $this->setCache($prototypeClass, 'properties', $ds->exportUxonObject());
        
        return $ds;
    }
    
    protected function getCache(string $prototypeClass, string $key)
    {
        return $this->getWorkbench()->getCache()->getPool('uxon.schema')->get($key . '.' . str_replace(WorkbenchCache::KEY_RESERVED_CHARS, '.', $prototypeClass));
    }
    
    protected function setCache(string $prototypeClass, string $key, $data) : UxonSchema
    {
        $this->getWorkbench()->getCache()->getPool('uxon.schema')->set($key . '.' . str_replace(WorkbenchCache::KEY_RESERVED_CHARS, '.', $prototypeClass), $data);
        return $this;
    }

    /**
     * Returning filename of definition for a given prototype class name
     * 
     * @param string $prototypeClass
     * @return string
     */
    public function getFilenameForEntity(string $prototypeClass) : string
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
     * @see \exface\Core\Interfaces\UxonSchemaInterface::getPropertiesByAnnotation()
     */
    public function getPropertiesByAnnotation(string $annotation, $value, string $prototypeClass = null) : array
    {
        $colName = mb_strtoupper(StringDataType::substringAfter($annotation, '@uxon-', $annotation, false));
        $ds = $this->getPropertiesSheet($prototypeClass ?? $this->getDefaultPrototypeClass());
        if (! $col = $ds->getColumns()->get($colName)) {
            return [];
        }
        
        $props = [];
        $rowNos = $col->findRowsByValue($value, false);
        foreach ($rowNos as $rowNo) {
            $props[] = $ds->getCellValue('PROPERTY', $rowNo);
        }
        return $props;
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
        $firstType = $this->getUxonType($uxon, $path, $rootPrototypeClass);
        
        try {
            $object = $this->getMetaObject($uxon, $path, $rootObject);
        } catch (MetaObjectNotFoundError $e) {
            // TODO better error handling to tell apart invalid object alias and no object alias.
            if ($rootObject !== null) {
                $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
            }
            $object = null;
        }
        
        return $this->getValidValuesForType($firstType, $search, $object);
    }
    
    public function getUxonType(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : ?string
    {
        $prop = mb_strtolower(end($path));
        if (true === is_numeric($prop)) {
            // If we are in an array, use the data from the parent property (= the array)
            // for every item within the array.
            $prop = mb_strtolower($path[(count($path)-2)]);
            $prototypeClass = $this->getPrototypeClass($uxon, $path, $rootPrototypeClass);
            $propertyTypes = $this->getPropertyTypes($prototypeClass, $prop);
            $firstType = trim($propertyTypes[0]);
            $firstType = rtrim($firstType, "[]");
        } else {
            // In all other cases, try to find something for the top-most property in the path
            $prototypeClass = $this->getPrototypeClass($uxon, $path, $rootPrototypeClass);
            $propertyTypes = $this->getPropertyTypes($prototypeClass, $prop);
            $firstType = trim($propertyTypes[0]);
        }
        
        return $firstType;
    }
    
    protected function getValidValuesForType(string $type, string $search = null, MetaObjectInterface $object = null) : array
    {
        $options = [];
        
        switch (true) {
            case $this->isPropertyTypeEnum($type) === true:
                $options = explode(',', trim($type, "[]"));
                break;
            case strcasecmp($type, 'boolean') === 0:
                $options = ['true', 'false'];
                break;
            case strcasecmp($type, 'timezone') === 0:
                $options = TimeZoneDataType::getKeysStatic();
                break;
            case strcasecmp($type, 'metamodel:data_source') === 0:
                $options = $this->getMetamodelDataSourceAliases($search);
                break;
            case strcasecmp($type, 'metamodel:connection') === 0:
                $options = $this->getMetamodelConnectionAliases($search);
                break;
            case strcasecmp($type, 'metamodel:widget') === 0:
                $options = $this->getMetamodelWidgetTypes();
                break;
            case strcasecmp($type, 'metamodel:object') === 0:
                $options = $this->getMetamodelObjectAliases($search);
                break;
            case strcasecmp($type, 'metamodel:action') === 0:
                $options = $this->getMetamodelActionAliases($search);
                break;
            case strcasecmp($type, 'metamodel:formula') === 0:
                $options = $this->getMetamodelFormulaAliases($search);
                break;
            case strcasecmp($type, 'metamodel:page') === 0:
                $options = $this->getMetamodelPageAliases($search);
                break;
            case strcasecmp($type, 'metamodel:comparator') === 0:
                $options = $this->getMetamodelComparators($search);
                break;
            case strcasecmp($type, 'metamodel:attribute') === 0 && $object !== null:
            case strcasecmp($type, 'metamodel:relation') === 0 && $object !== null:
                try {
                    if (strcasecmp($type, 'metamodel:attribute') === 0) {
                        $options = $this->getMetamodelAttributeAliases($object, $search);
                    } else {
                        $options = $this->getMetamodelRelationAliases($object, $search);
                    }
                } catch (MetaObjectNotFoundError $e) {
                    $options = [];
                }
                break;
            case strcasecmp($type, 'metamodel:expression') === 0:
                try {
                    
                    // Formula: directly determine existing aliases without metaobject
                    if (substr($search, 0, 1) === '=') {
                        $type = 'metamodel:formula';
                        $options = $this->getMetamodelFormulaExpressions($search);
                        break;
                    }
                    
                    $ex = ExpressionFactory::createFromString($this->getWorkbench(), $search, $object);
                    
                    if ($ex->isReference() === true) {
                        // TODO
                    } elseif ($ex->isNumber()) {
                        // Do nothing - a number is simply a number
                    } elseif ($object !== null) {
                        // If the expression is neither of the above, try to interpret it as an attribute
                        $options = $this->getMetamodelAttributeAliases($object, $search);
                    } else {
                        $options = [];
                    }
                } catch (MetaObjectNotFoundError $e) {
                    $options = [];
                }
                break;
            case strcasecmp($type, 'metamodel:aggregator') === 0:
                $options = AggregatorFunctionsDataType::getKeysStatic();
                break;
            case strcasecmp($type, 'metamodel:datatype') === 0:
                $options = $this->getMetamodelDatatypeAliases();
                break;
            case strcasecmp($type, 'metamodel:event') === 0:
                $options = $this->getMetamodelEventAliases();
                break;
            case StringDataType::startsWith($type, 'metamodel:'):
                $options = $this->getMetamodelData($type, $search);
                break;
            case $this->isPropertyTypeObject($type) === true:
                list ($keyType, $valType) = explode('=>', trim($type, "{}"));
                $keyType = trim($keyType);
                $valType = trim($valType);
                if (! $valType) {
                    $valType = $keyType;
                }
                $options = $valType ? $this->getValidValuesForType($valType, $search, $object) : [];
                break;
        }
        return $options;
    }
    
    /**
     * 
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
     * Returning metamodel object aliases
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
            if (count($parts) > 1) {
                $alias = $parts[2];
                $namespace = $parts[0] . ($parts[1] !== null ? '.' . $parts[1] : '');
            } else {
                $alias = $parts[0];
            }
            $ds->getFilters()->addConditionFromString('APP__ALIAS', $namespace);
            $ds->getFilters()->addConditionFromString('ALIAS', $alias);
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
     * Returning metamodel attribute aliases
     * 
     * @param MetaObjectInterface $object
     * @param string $search
     * @return string[]
     */
    protected function getMetamodelAttributeAliases(MetaObjectInterface $object, string $search = null) : array
    {
        $rels = $search !== null ? (RelationPath::relationPathParse($search) ?? []) : [];
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
        // Reverse relations and 1-to-1 relations coming from other objects are not attributes, 
        // so we need to add them here manually
        foreach ($object->getRelations() as $rel) {
            $val = ($relPath ? $relPath . RelationPath::RELATION_SEPARATOR : '') . $rel->getAliasWithModifier() . RelationPath::RELATION_SEPARATOR;
            if (! in_array($val, $value_relations)) {
                $values[] = $val; 
            }
        }
        
        // Sort attributes and reverse relations alphabetically.
        sort($values);
        
        // Now insert forward relations _before_ the corresponding attribute: relation attributes rarely
        // get used directly, but rather as parts of a relation path.
        foreach ($value_relations as $val) {
            $idx = array_search(rtrim($val, RelationPath::RELATION_SEPARATOR), $values, true);
            if ($idx !== false) {
                array_splice($values, $idx, 0, [$val]);
            } else {
                $values[] = $val;
            }
        }
        
        return $values;
    }
    
    /**
     * Returning metamodel relation aliases
     * 
     * @param MetaObjectInterface $object
     * @param string $search
     * @return array
     */
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
     * Returning metamodel widget types
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
     * Returning metamodel datatype aliases
     *
     * @return string[]
     */
    protected function getMetamodelDatatypeAliases() : array
    {
        if ($cache = $this->getCache('', 'datatypeAliases')) {
            return $cache;
        }
        
        $options = [];
        $dot = AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;

        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.DATATYPE');
        $ds->getColumns()->addMultiple(['ALIAS', 'APP__ALIAS']);
        $ds->dataRead();
        foreach ($ds->getRows() as $row) {
            $options[] = $row['APP__ALIAS'] . $dot . $row['ALIAS'];
        }
        sort($options);
        
        $this->setCache('', 'datatypeAliases', $options);

        return $options;
    }
    
    /**
     * Returning metamodel datatype aliases
     *
     * @return string[]
     */
    protected function getMetamodelEventAliases() : array
    {

        if ($cache = $this->getCache('', 'eventAliases')) {
            return $cache;
        }
        
        $options = [];
        $dot = AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;

        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.EVENT');
        $ds->getColumns()->addMultiple(['NAME', 'PATH_RELATIVE']);
        $ds->dataRead();
        foreach ($ds->getRows() as $row) {
            $namespace = str_replace(['/Events', '/'], ['', $dot], $row['PATH_RELATIVE']);
            $alias = $namespace . $dot . $row['NAME'];
            if (StringDataType::endsWith($alias, 'Event')) {
                $alias = StringDataType::substringBefore($alias, 'Event', $alias, true, true);
            }
            $options[] = $alias;
        }
        sort($options);
        
        $this->setCache('', 'eventAliases', $options);
         
        return $options;
    }
    
    /**
     * Returning metamodel action aliases
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
     * Returning formula aliases: e.g. ['exface.Core.Concatenate', 'Concatenate', 'my.App.MyFormula']
     * 
     * @return string[]
     */
    protected function getMetamodelFormulaAliases() : array
    {
        
        if ($cache = $this->getCache('', 'formulaAliases')) {
             return $cache;
        }
        
        $options = [];
        $dot = AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER;
        
        // Prototypes
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.FORMULA');
        $ds->getColumns()->addMultiple(['NAME', 'PATH_RELATIVE']);
        $ds->dataRead();
        foreach ($ds->getRows() as $row) {
            $namespace = str_replace(['/Formulas', '/'], ['', $dot], $row['PATH_RELATIVE']);
            $options[] = $namespace . $dot . $row['NAME'];
            if (strcasecmp($namespace, 'exface.Core') === 0) {
                $options[] = $row['NAME'];
            }
        }
       sort($options);
      
       $this->setCache('', 'formulaAliases', $options);
        
        return $options;
    }
    
    
    /**
     * Returning formula expression stubs: e.g. ['=exface.Core.Concatenate(', '=Concatenate(', '=my.App.MyFormula(']
     * 
     * @return string[]
     */
    protected function getMetamodelFormulaExpressions(string $search = null) : array
    {  

       $expressions = []; 
        
       foreach ($this->getMetamodelFormulaAliases($search) as $key => $opt) {
           $expressions[$key] = '=' . $opt . '(';
       }
       
       return $expressions;
    }
    
    
    
    /**
     * Returning metamodel page aliases
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
     * Returning metamodel data source aliases
     *
     * @return string[]
     */
    protected function getMetamodelDataSourceAliases() : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.DATASRC');
        $ds->getColumns()->addFromExpression('ALIAS_WITH_NS');
        $ds->dataRead();
        return $ds->getColumns()->get('ALIAS_WITH_NS')->getValues(false);
    }
    
    /**
     *
     * Returning metamodel connection aliases
     *
     * @return string[]
     */
    protected function getMetamodelConnectionAliases() : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.CONNECTION');
        $ds->getColumns()->addFromExpression('ALIAS');
        $ds->dataRead();
        return $ds->getColumns()->get('ALIAS')->getValues(false);
    }
    
    /**
     * 
     * Returning metamodel comparators
     * 
     * @return string[]
     */
    protected function getMetamodelComparators() : array
    {
        return array_values(ComparatorDataType::getValuesStatic());
    }
    
    /**
     * Returning true, if property has type enumeration
     * 
     * @param string $type
     * @return bool
     */
    protected function isPropertyTypeEnum(string $type) : bool
    {
        return substr($type, 0, 1) === '[' && substr($type, -1) === ']';
    }
    
    /**
     * Returning true, if property has type object
     * 
     * E.g. for {} or {string => metamodel:attribute}, etc.
     *
     * @param string $type
     * @return bool
     */
    protected function isPropertyTypeObject(string $type) : bool
    {
        return substr($type, 0, 1) === '{' && substr($type, -1) === '}';
    }
    
    /**
     * 
     * Returns true, if given prototype class name is an existing class
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
        
        if ($class === null || is_a($this, $class, true)) {
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
    
    /**
     *
     * {@inheritdoc}
     * @see UxonSchemaInterface::getSchemaName()
     */
    public static function getSchemaName() : string
    {
        $thisClass = get_called_class();
        $genericClass = UxonSchema::class;
        if ($thisClass === $genericClass) {
            return UxonSchemaNameDataType::GENERIC;
        } else {
            return '\\' . $thisClass;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\UxonSchemaInterface::getPresets()
     */
    public function getPresets(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array
    {
        $presets = [];
        
        $prototypeClass = $this->getPrototypeClass($uxon, $path, $rootPrototypeClass, true);
        $schema = $this->getSchemaForClass($prototypeClass);
        
        if ($schema !== $this) {
            return $schema->getPresets($uxon, $path, $rootPrototypeClass);
        }
        
        $schemaNameUC = strtoupper($this::getSchemaName());
        switch ($schemaNameUC) {
            case 'CONNECTION':
            case 'BEHAVIOR':
            case 'DATATYPE':
            case 'ACTION':
            case 'WIDGET':
                $objectAlias = 'exface.Core.' . $schemaNameUC . '_PRESET';
                break;
            default:
                $objectAlias = 'exface.Core.UXON_PRESET';
                
        }
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objectAlias);
        $ds->getColumns()->addMultiple(['UID','NAME', 'PROTOTYPE__LABEL', 'DESCRIPTION', 'PROTOTYPE', 'UXON' , 'WRAP_PATH', 'WRAP_FLAG', 'THUMBNAIL']);
        $ds->getFilters()->addConditionFromString('UXON_SCHEMA', $schema::getSchemaName(), ComparatorDataType::EQUALS);
        $ds->getSorters()
        ->addFromString('PROTOTYPE', SortingDirectionsDataType::ASC)
        ->addFromString('NAME', SortingDirectionsDataType::ASC);
        $ds->dataRead();
        
        foreach ($ds->getRows() as $row) {
            // TODO: Leerer Editor, oberste Knoten => class ist abstract widget => keine Filterung
            // Class Plus Wrapper-Presets (ausser AbstractWidget)
            $presets[] = $row;
        }
        
        return $presets;
    }
    
    protected function getMetamodelData(string $uxonType, string $search = null) : array
    {
        list($prefix, $object, $attribute) = explode(':', $uxonType, 3);
        if (mb_strtolower($prefix) !== 'metamodel' || $object === null || $attribute === null) {
            return [];
        }
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $object);
        $col = $ds->getColumns()->addFromExpression($attribute);
        $ds->dataRead();
        return $col->getValues(false);
    }
}