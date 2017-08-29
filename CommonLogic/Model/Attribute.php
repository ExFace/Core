<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\DataTypeInterface;
use exface\Core\CommonLogic\Constants\SortingDirections;
use exface\Core\Exceptions\Model\MetaObjectModelError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class Attribute implements ExfaceClassInterface, iCanBeCopied
{

    // Properties to be dublicated on copy()
    private $id;

    private $object_id;

    private $inherited_from_object_id = null;

    private $alias;

    private $name;

    private $data;

    private $data_address_properties;

    private $data_type;

    private $formatter;

    private $required = false;

    private $hidden = false;

    private $editable = false;

    private $system = false;

    private $default_display_order;

    private $is_relation;

    private $formula;

    private $default_value;

    private $fixed_value;
    
    private $value_list_delimiter = EXF_LIST_SEPARATOR;

    private $default_sorter_dir = 'ASC';

    private $short_description;

    private $defaul_aggregate_function = null;

    private $sortable;

    private $filterable;

    private $aggregatable;

    /** @var UxonObject */
    private $default_widget_uxon;

    /** @var RelationPath */
    private $relation_path;

    // Properties NOT to be dublicated on copy()
    /** @var Model */
    private $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Marks this attribute as a relation
     * 
     * @param boolean $value            
     */
    public function setRelationFlag($value)
    {
        $this->is_relation = $value;
    }

    /**
     * Returns TRUE if this attribute actually is a relation and FALSE otherwise.
     * The relation itself can be obtained by calling get_relation().
     * 
     * @see getRelation()
     * @return boolean
     */
    public function isRelation()
    {
        return $this->is_relation;
    }

    /**
     * Returns the relation, this attribute represents if it is a relation attribute and NULL otherwise
     * 
     * @return Relation
     */
    public function getRelation()
    {
        return $this->getObject()->getRelation($this->getAlias());
    }

    public function getAliasWithRelationPath()
    {
        return RelationPath::relationPathAdd($this->getRelationPath()->toString(), $this->getAlias());
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function setAlias($value)
    {
        $this->alias = $value;
    }

    /**
     * Returns the data type of the attribute as an instantiated data type object
     * 
     * @return DataTypeInterface
     */
    public function getDataType()
    {
        if (is_string($this->data_type)){
            $this->data_type = DataTypeFactory::createFromAlias($this->getWorkbench(), $this->data_type);
        }
        return $this->data_type;
    }
    
    /**
     * 
     * @param string|DataTypeInterface $object_or_name
     * @throws UnexpectedValueException
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function setDataType($object_or_name)
    {
        if (is_string($object_or_name) || ($object_or_name instanceof DataTypeInterface)) {
            $this->data_type = $object_or_name;
        } else {
            throw new UnexpectedValueException('Invalid data type value given to attribute "' . $this->getAliasWithRelationPath() . '" of object "' . $this->getObject()->getAliasWithNamespace() . '": string or instantiated data type classes expected!');
        }
        return $this;
    }

    public function getDefaultDisplayOrder()
    {
        return $this->default_display_order;
    }

    public function setDefaultDisplayOrder($value)
    {
        $this->default_display_order = $value;
    }

    /**
     * Returns TRUE if the attribute can be changed and FALSE if it is read only.
     * Attributes of objects from read-only data sources are never editable!
     * 
     * @return boolean
     */
    public function isEditable()
    {
        if ($this->getObject()->getDataSource()->isReadOnly()) {
            return false;
        }
        return $this->editable;
    }

    public function setEditable($value)
    {
        $this->editable = $value;
    }

    /**
     *
     * @return unknown
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     *
     * @param unknown $value            
     */
    public function setFormatter($value)
    {
        $this->formatter = $value;
    }

    public function isHidden()
    {
        return $this->hidden;
    }

    public function setHidden($value)
    {
        $this->hidden = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function isRequired()
    {
        return $this->required;
    }

    public function setRequired($value)
    {
        $this->required = $value;
    }

    public function getDataAddress()
    {
        return $this->data;
    }

    public function setDataAddress($value)
    {
        $this->data = $value;
    }

    /**
     * Returns the relation path for this attribute, no matter how deep
     * the relation is.
     * E.g. calling it for the attribute PRICE of POSITION__PRODUCT
     * (POSITION__PRODUCT__PRICE) would result in POSITION__PRODUCT as
     * path.
     * Returns NULL if the attribute belongs to the object itself.
     *
     * @return RelationPath
     */
    public function getRelationPath()
    {
        if (is_null($this->relation_path)) {
            $this->relation_path = RelationPathFactory::createForObject($this->getObject());
        }
        return $this->relation_path;
    }

    protected function setRelationPath(RelationPath $path)
    {
        $this->relation_path = $path;
    }

    /**
     * Returns the meta object to which this attributes belongs to.
     * If the attribute has a relation path, this
     * will return the last object in that path.
     *
     * If the attribute is inherited, the inheriting object will be returned. To get the base object, the
     * attribute was inherited from, use getObjectInheritedFrom().
     *
     * @return \exface\Core\CommonLogic\Model\Object
     */
    public function getObject()
    {
        return $this->getModel()->getObject($this->getObjectId());
    }

    /**
     * Returns the object, this attribute was inherited from.
     * If the attribute was not inherited, returns it's regular object (same as get_object()).
     *
     * If the attribute was inherited multiple times, this method will go back exactly one step. For example, if we have a base object
     * of a data source, that is extended by OBJECT1, which in turn, is extended by OBJECT2, calling get_object_extended_from() on an
     * attribute of OBJECT2 will return OBJECT1, while doing so for OBJECT1 will return the base object.
     *
     * @return \exface\Core\CommonLogic\Model\Object
     */
    public function getObjectInheritedFrom()
    {
        if ($this->isInherited()) {
            return $this->getModel()->getObjectById($this->getInheritedFromObjectId());
        } else {
            return $this->getObject();
        }
    }

    /**
     * Returns a UXON object for the default editor widget for this attribute.
     * 
     * The default widget can be defined for a data type and extended by a further definition for a specific attribute. If none of the above is defined,
     * a blank UXON object with merely the overall default widget type (specified in the config) will be returned.
     * 
     * The returned UXON is a copy. Changes on it will not affect the result of the next method call. If you need to change
     * the default UXON use Attribute::setDefaultWidgetUxon(Attribute::getDefaultWidgetUxon()) or similar.
     * 
     * @return UxonObject
     */
    public function getDefaultWidgetUxon()
    {
        $uxon = $this->default_widget_uxon->copy();
        
        if (! $uxon->getProperty('attribute_alias')) {
            $uxon->setProperty(attribute_alias, $this->getAliasWithRelationPath());
        }
        return $uxon;
    }

    public function setDefaultWidgetUxon(UxonObject $uxon_object)
    {
        $this->default_widget_uxon = $uxon_object;
    }

    public function getFormula()
    {
        return $this->formula;
    }

    public function setFormula($value)
    {
        if ($value) {
            $this->formula = $this->getModel()->parseExpression($value, $this->getObject());
        }
    }

    /**
     * Returns an expression for the default value of this attribute, which is to be used, when saving the attribute without an explicit value given in the data sheet.
     * 
     * @see getFixedValue() in contrast to the fixed value, the default value is always overridden by any value in the data sheet.
     * @return \exface\Core\CommonLogic\Model\Expression
     */
    public function getDefaultValue()
    {
        if ($this->default_value && ! ($this->default_value instanceof expression)) {
            $this->default_value = $this->getModel()->parseExpression($this->default_value, $this->getObject());
        }
        return $this->default_value;
    }

    public function setDefaultValue($value)
    {
        if ($value) {
            $this->default_value = $value;
        }
    }

    /**
     * Returns an expression for value of this attribute, which is to be set or updated every time the attribute is saved to the data source.
     * 
     * @return \exface\Core\CommonLogic\Model\Expression
     */
    public function getFixedValue()
    {
        if ($this->fixed_value && ! ($this->fixed_value instanceof expression)) {
            $this->fixed_value = $this->getModel()->parseExpression($this->fixed_value, $this->getObject());
        }
        return $this->fixed_value;
    }

    public function setFixedValue($value)
    {
        $this->fixed_value = $value;
    }

    /**
     * 
     * @return \exface\Core\CommonLogic\Constants\SortingDirections
     */
    public function getDefaultSorterDir()
    {
        return $this->default_sorter_dir ? $this->default_sorter_dir : $this->getDataType()->getDefaultSortingDirection();
    }

    /**
     * 
     * @param SortingDirections|string $value
     */
    public function setDefaultSorterDir($value)
    {        
        if ($value instanceof SortingDirections){
            // everything is OK
        } elseif (SortingDirections::isValid(strtolower($value))){
            $value = new SortingDirections(strtolower($value));
        } else {
            throw new UnexpectedValueException('Invalid value "' . $value . '" for default sorting direction in attribute "' . $this->getName() . '": use ASC or DESC');
        }
        
        $this->default_sorter_dir = $value;
        return $this;
    }

    public function getObjectId()
    {
        return $this->object_id;
    }

    public function setObjectId($value)
    {
        $this->object_id = $value;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setModel(\exface\Core\CommonLogic\Model\Model $model)
    {
        $this->model = $model;
    }

    public function getShortDescription()
    {
        return $this->short_description;
    }

    public function setShortDescription($value)
    {
        $this->short_description = $value;
    }

    public function getHint()
    {
        return ($this->getShortDescription() ? $this->getShortDescription() : $this->getName()) . ' [' . $this->getDataType()->getName() . ']';
    }

    /**
     * Returns the UID of the object, this attribute was inherited from or NULL if it is a direct attribute of it's object
     * 
     * @return string
     */
    public function getInheritedFromObjectId()
    {
        return $this->inherited_from_object_id;
    }

    /**
     *
     * @param string $value            
     */
    public function setInheritedFromObjectId($value)
    {
        $this->inherited_from_object_id = $value;
    }

    /**
     * Returns TRUE if this Relation was inherited from a parent object
     * 
     * @return boolean
     */
    public function isInherited()
    {
        return is_null($this->getInheritedFromObjectId()) ? true : false;
    }

    /**
     *
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function getDataAddressProperties()
    {
        return $this->data_address_properties;
    }

    /**
     *
     * @param UxonObject $value            
     * @return Attribute
     */
    public function setDataAddressProperties(UxonObject $value)
    {
        $this->data_address_properties = $value;
        return $this;
    }

    /**
     * Returns the value of a data source specifi object property specified by it's id
     * 
     * @param string $id            
     */
    public function getDataAddressProperty($id)
    {
        return $this->getDataAddressProperties()->getProperty($id);
    }

    /**
     *
     * @param string $id            
     * @param mixed $value            
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function setDataAddressProperty($id, $value)
    {
        $this->getDataAddressProperties()->setProperty($id, $value);
        return $this;
    }

    /**
     * Returns TRUE if the attribute is used as the label for it's object or FALSE otherwise
     * 
     * @return boolean
     */
    public function isLabel()
    {
        if ($this->getAlias() == $this->getObject()->getLabelAlias()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns TRUE if this attribute is used as UID for it's object and FALSE otherwise
     * 
     * @return boolean
     */
    public function isUidForObject()
    {
        if ($this->getObject()->getUidAlias() === $this->getAlias()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns TRUE if this attribute is the same (same UID, same object), as the given attribute, and FALSE otherwise.
     *
     * This method will also return TRUE if the attributes have differen relations paths.
     * NOTE: comparing the UID is not enough, as inherited attributes will keep their UID.
     *
     * @param Attribute $attribute            
     * @return boolean
     */
    public function isExactly(Attribute $attribute)
    {
        if ($this->getId() == $attribute->getId() && $this->getObject()->isExactly($attribute->getObject())) {
            return true;
        }
        return false;
    }

    /**
     * Returns TRUE if the given attribute is the same as this one or is inherited from it.
     *
     * For example, if we have a BASE object for a data source holds the UID attribute, and OBJECT1 inherits from that base object,
     * we will have the following behavior - even if there is a custom UID attribute for OBJECT1, that overrides the default:
     * - BASE__UID->is(OBJECT1__UID) = FALSE
     * - OBJECT__UID->is(BASE__UID) = TRUE
     * - BASE__UID->is(BASE__UID) = TRUE
     * - OBJECT1__UID->is(OBJECT1__UID) = TRUE
     *
     * @param Attribute $attribute            
     * @return boolean
     */
    public function is(Attribute $attribute)
    {
        if (strcasecmp($this->getAlias(), $attribute->getAlias()) === 0 && $this->getObject()->is($attribute->getObject())) {
            return true;
        }
        return false;
    }

    /**
     * Creates an exact copy of the attribute
     *
     * @return Attribute
     */
    public function copy()
    {
        return $this->rebase($this->getRelationPath()->copy());
    }

    /**
     * Creates a copy of the attribute relative to a given relation path.
     * This is usefull if you want to rebase an attribute.
     *
     * @param RelationPath $path            
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function rebase(RelationPath $path)
    {
        $copy = clone $this;
        
        // Explicitly copy properties, that are objects themselves
        $copy->setRelationPath($path);
        // Do not use getDefaultWidgetUxon() here as it already performs some enrichment
        $copy->setDefaultWidgetUxon($this->default_widget_uxon->copy());
        return $copy;
    }

    /**
     * Returns TRUE if this attribute is a system attribute.
     * System attributes are required by the internal logic
     * (like the UID attribute) an will be loaded by default in all data sheets
     *
     * @return boolean
     */
    public function isSystem()
    {
        return $this->system;
    }

    /**
     * Marks the attribute as system (TRUE) or non-system (FALSE).
     * System attributes are required by the internal logic (like the UID attribute) an will be loaded by default
     * in all data sheets
     *
     * @param boolean $value            
     * @return Attribute
     */
    public function setSystem($value)
    {
        $this->system = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * @return Workbench
     */
    public function getWorkbench()
    {
        return $this->getModel()->getWorkbench();
    }

    /**
     *
     * @return string
     */
    public function getDefaultAggregateFunction()
    {
        return $this->default_aggregate_function;
    }

    /**
     *
     * @param string $value            
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function setDefaultAggregateFunction($value)
    {
        $this->default_aggregate_function = $value;
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function isSortable()
    {
        return $this->sortable;
    }

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function setSortable($value)
    {
        $this->sortable = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function isFilterable()
    {
        return $this->filterable;
    }

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function setFilterable($value)
    {
        $this->filterable = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function isAggregatable()
    {
        return $this->aggregatable;
    }

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function setAggregatable($value)
    {
        $this->aggregatable = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }
    
    /**
     * Returns the delimiter to be used when concatennating multiple values of 
     * this attribute into a string.
     * 
     * Defaults to EXF_LIST_SEPARATOR unless changed via setValueListDelimiter()
     * 
     * @return string
     */
    public function getValueListDelimiter()
    {
        return $this->value_list_delimiter;
    }
    
    /**
     * Changes the delimiter to be used when concatennating multiple values of 
     * this attribute into a string.
     * 
     * This is usefull if the values are likely to contain the delimiter string
     * themselves. Since the default delimiter is a comma, you should change it
     * to something else if your values will regularly contain commas. 
     * 
     * Note, for longer texts, that will contain commas in most cases, there is
     * normally no need to change the delimiter because it is mainly used for
     * all kinds of filter and relation keys. Longer texts with commas, on the 
     * other hand, are very unlikely to be used for keys or as search strings.
     * If it still happens, change the delimiter to a pipe or so, that - in
     * turn - is very unlikely to be included in a longer text.
     * 
     * @param string $string
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function setValueListDelimiter($string)
    {
        if (!is_null($string) && $string !== ''){
            $this->value_list_delimiter = $string;
        }
        return $this;
    }
 
}
?>