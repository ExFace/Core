<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\MetaAttributeOriginDataType;
use exface\Core\DataTypes\MetaAttributeTypeDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\UUIDDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Model\MetaObjectModelError;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\Interfaces\Selectors\AttributeGroupSelectorInterface;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use Throwable;

/**
 * A regular meta object attribute
 *
 * @author Andrej Kabachnik
 *
 */
class Attribute implements MetaAttributeInterface, iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;
    
    // Properties to be dublicated on copy()
    private $id;

    private $inherited_from_object_id = null;

    private $inheritedOriginalAttribute = null;

    private $alias;

    private $name;

    private $data;

    private $data_address_properties;

    private $data_type_selector = null;

    private $data_type = null;

    private $calculationString = null;
    
    private $readable = true;
    
    private $writable = null;

    private $required = false;

    private $hidden = false;

    private $editable = false;
    
    private $copyable = true;

    private $system = false;

    private $default_display_order;

    private $default_aggregate_function;

    private $is_relation;

    private $formula;

    private $default_value = null;

    private $fixed_value = null;
    
    private $value_list_delimiter = EXF_LIST_SEPARATOR;

    private $default_sorter_dir = 'ASC';

    private $short_description;

    private $defaul_aggregate_function = null;

    private $sortable;

    private $filterable;

    private $aggregatable;
    
    /** @var UxonObject|null */
    private $default_editor_uxon = null;
    
    private $default_editor_uxon_string = null;
    
    /** @var UxonObject|null */
    private $default_display_uxon = null;
    
    private $default_display_uxon_string;

    /** @var UxonObject|null */
    private $custom_data_type_uxon = null;
    
    private $custom_data_type_string = null;

    /** @var MetaRelationPathInterface|null */
    private $relation_path;

    private $groupSelectors = [];

    private $groups = [];

    // Properties NOT to be dublicated on copy()
    /** @var Model */
    private $object;

    private $attributeType = MetaAttributeTypeDataType::GENERATED;

    public function __construct(MetaObjectInterface $object, string $name, string $alias)
    {
        $this->object = $object;
        $this->name = $name;
        $this->alias = $alias;
        $this->id = UUIDDataType::generateSqlOptimizedUuid();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setRelationFlag()
     */
    public function setRelationFlag($value)
    {
        $this->is_relation = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isRelation()
     */
    public function isRelation()
    {
        return $this->is_relation;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getRelation()
     */
    public function getRelation()
    {
        return $this->getObject()->getRelation($this->getAlias());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getAliasWithRelationPath()
     */
    public function getAliasWithRelationPath()
    {
        return RelationPath::join($this->getRelationPath()->toString(), $this->getAlias());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getId()
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setId()
     */
    public function setId($value)
    {
        $this->id = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setAlias()
     */
    public function setAlias($value)
    {
        $this->alias = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDataType()
     */
    public function getDataType()
    {
        switch (true) {
            case $this->data_type !== null:
                return $this->data_type;
            case $this->data_type_selector !== null:
                try {
                    if ($this->data_type_selector instanceof DataTypeSelectorInterface) {
                        $this->data_type = DataTypeFactory::createFromSelector($this->data_type_selector)->copy();
                    } else {
                        $this->data_type = DataTypeFactory::createFromString($this->getWorkbench(), $this->data_type_selector)->copy();
                    }
                    $this->data_type->importUxonObject($this->getCustomDataTypeUxon());
                } catch (\Throwable $e) {
                    throw new MetaObjectModelError($this->getObject(), 'Cannot initialize data type for attribute ' . $this->__toString() . ' of object ' . $this->getObject()->__toString() . '. ' . $e->getMessage(), null, $e);
                }
                break;
            case $this->custom_data_type_uxon !== null:
                try {
                    $this->data_type = DataTypeFactory::createFromUxon($this->getWorkbench(), $this->getCustomDataTypeUxon());
                } catch (\Throwable $e) {
                    throw new MetaObjectModelError($this->getObject(), 'Cannot initialize data type for attribute ' . $this->__toString() . ' of object ' . $this->getObject()->__toString() . '. ' . $e->getMessage(), null, $e);
                }
                break;
            default: 
                throw new UnexpectedValueException('Invalid data type value given to attribute "' . $this->getAliasWithRelationPath() . '" of object ' . $this->getObject()->__toString() . ': expecting a selector, a valid UXON or a data type class instance!');
        }
        return $this->data_type;
    }
    
    /**
     * The data type of the attribute
     *
     * @uxon-property data_type
     * @uxon-type \exface\Core\CommonLogic\DataTypes\AbstractDataType
     * @uxon-template {"alias": ""}
     * 
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDataType()
     */
    public function setDataType($instanceOrSelectorOrUxon)
    {
        switch (true) {
            case is_string($instanceOrSelectorOrUxon):
            case $instanceOrSelectorOrUxon instanceof DataTypeSelectorInterface:
                $this->data_type_selector = $instanceOrSelectorOrUxon;
                $this->data_type = null;
                break;
            case $instanceOrSelectorOrUxon instanceof DataTypeInterface:
                $this->data_type_selector = $instanceOrSelectorOrUxon->getAliasWithNamespace();
                $this->data_type = $instanceOrSelectorOrUxon;
                break;
            case $instanceOrSelectorOrUxon instanceof UxonObject:
                $this->setCustomDataTypeUxon($instanceOrSelectorOrUxon);
                break;
            default: 
                throw new UnexpectedValueException('Invalid data type value given to attribute "' . $this->getAliasWithRelationPath() . '" of object "' . $this->getObject()->getAliasWithNamespace() . '": expecting selector, a valid UXON or a data type class instance - received "' . gettype($instanceOrSelectorOrUxon) . '"!');
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultDisplayOrder()
     */
    public function getDefaultDisplayOrder()
    {
        return $this->default_display_order;
    }

    /**
     * Set to a positive number to make this attribute appear in data widgets if no columns are specified explicitly.
     * 
     * @uxon-property default_display_order
     * @uxon-type integer
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultDisplayOrder()
     */
    public function setDefaultDisplayOrder($value)
    {
        $this->default_display_order = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isEditable()
     */
    public function isEditable()
    {
        return $this->editable;
    }

    /**
     * Set to TRUE to make this attribute editable in the UI and FALSE to hide it from editors by default
     * 
     * @uxon-property editable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setEditable()
     */
    public function setEditable($value)
    {
        $this->editable = BooleanDataType::cast($value);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getCalculationExpression()
     */
    public function getCalculationExpression() : ?ExpressionInterface
    {
        $expr = null;
        if ($this->calculationString !== null) {
            $expr = ExpressionFactory::createForObject($this->getObject(), $this->calculationString);
            if ($this->getRelationPath()->isEmpty() === false) {
                $expr = $expr->withRelationPath($this->getRelationPath());
            }
        }
        return $expr;
    }

    /**
     * Formula to calculate data values of this attribute (instead or in addition to a data address)
     *
     * E.g. `=Concatenate(attribute1, attribute2)`.
     *
     * Normally used instead of a data address to add read-only attributes the data source cannot produce.
     * In some cases a combination of a formula and a data address may be used to add specific formatting to a
     * value (e.g. a data stored in some non-standard-format).
     *
     * @uxon-property calculation
     * @uxon-type metamodel:formula
     *
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setCalculation()
     */
    public function setCalculation(string $expressionString) : MetaAttributeInterface
    {
        $this->calculationString = $expressionString;
        return $this;
    }
    
    public function hasCalculation() : bool
    {
        return $this->calculationString !== null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isHidden()
     */
    public function isHidden()
    {
        return $this->hidden;
    }

    /**
     * Set to TRUE to mark this attribute as hidden
     * 
     * @uxon-property hidden
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setHidden()
     */
    public function setHidden($value)
    {
        $this->hidden = BooleanDataType::cast($value);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setName()
     */
    public function setName($value)
    {
        $this->name = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isReadable()
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * Set to FALSE to explicitly forbid reading this attribute or to TRUE to explicitly allow it
     * 
     * @uxon-property readable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setReadable()
     */
    public function setReadable($true_or_false)
    {
        $this->readable = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isWritable()
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * Set to FALSE to explicitly forbid writing this attribute or to TRUE to explicitly allow it
     * 
     * @uxon-property writable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setWritable()
     */
    public function setWritable($true_or_false)
    {
        $this->writable = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isRequired()
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Set to TRUE to mark the attribute as required (not nullable)
     * 
     * @uxon-property required
     * @uxon-type boolean
     * @uxon-default false
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setRequired()
     */
    public function setRequired($value)
    {
        $this->required = BooleanDataType::cast($value);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDataAddress()
     */
    public function getDataAddress()
    {
        return $this->data;
    }

    /**
     * The address in the data source - e.g. SQL for SQL data sources or parts of the URL for web services.
     * 
     * @uxon-property data_address
     * @uxon-type string
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDataAddress()
     */
    public function setDataAddress($value)
    {
        $this->data = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getRelationPath()
     */
    public function getRelationPath()
    {
        if (is_null($this->relation_path)) {
            $this->relation_path = RelationPathFactory::createForObject($this->getObject());
        }
        return $this->relation_path;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getObject()
     */
    public function getObject()
    {
        return $this->object;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function setObject(MetaObjectInterface $object)
    {
        $this->object = $object;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getObjectInheritedFrom()
     */
    public function getObjectInheritedFrom() : ?MetaObjectInterface
    {
        if ($this->isInherited()) {
            return $this->getModel()->getObjectById($this->inherited_from_object_id);
        }
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getInheritedOriginalAttribute()
     */
    public function getInheritedOriginalAttribute() : ?MetaAttributeInterface
    {
        return $this->inheritedOriginalAttribute;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::withExtendedObject()
     */
    public function withExtendedObject(MetaObjectInterface $newObject) : MetaAttributeInterface
    {
        // Copy the attribute ignoring its relation path. In fact, it should not have any relation path because
        // only direct attributes can be inherited. Not sure, if we need to double-check it somehow?
        // IDEA copying will also copy the UXON objects for the default editor/display widgets. Not quite sure
        // if this is correct. The way it is now, changes to the default editor of the original attribute will
        // not affect the inheriting one. It has always been this way though... Same goes for the data type.
        // Although, inheriting attributes are unlikely to change the data type aren't they?
        $clone = $this->copy(true);
            
        // Save the object, we are inheriting from in the attribute
        $clone->inherited_from_object_id = $this->getObject()->getId();
        // Save the very first attribute, that is being inherited - in case the attribute is inherited from object
        // to object multiple times
        $clone->inheritedOriginalAttribute = $this->inheritedOriginalAttribute ?? $this;
        
        // IDEA Is it a good idea to set the object of the inheridted attribute to the inheriting object? Would it be
        // better, if we only do this for objects, that do not have their own data address and merely are containers for attributes?
        //
        // Currently the attribute is attached to the inheriting object, but the reference to the original object is saved in the
        // inherited_from_object_id property. This is important because otherwise there is no easy way to find out, which object
        // the attribute belongs to. Say, we want to get the object filtered over if the filter attribute_alias is RELATION__RELATION__ATTRIBUTE
        // and ATTRIBUTE is inherited. In this case ATTRIBUTE->getObject() should return the inheriting object and not the base object.
        //
        // One place, this is used at is \exface\Core\Widgets\Data::doPrefill(). When trying to prefill from the filters of the prefill sheet,
        // we need to find a filter widget over the object the prefill filters attribute belong to. Now, if that attribute is a UID or a
        // create/update-timestamp, it will often be inherited from some base object of the data source - perhaps the same base object, the
        // widget's object inherits from as well. In this case, there is no way to know, whose UID it is, unless the object_id of the inherited
        // attribute points to the object it directly belongs to (working example in Administration > Core > App > Button "Show Objects").
        $clone->object = $newObject;

        return $clone;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getFormula()
     */
    public function getFormula()
    {
        return $this->formula;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setFormula()
     */
    public function setFormula($value)
    {
        if ($value) {
            $this->formula = $this->getModel()->parseExpression($value, $this->getObject());
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultValue()
     */
    public function getDefaultValue()
    {
        if ($this->hasDefaultValue() === true && ! ($this->default_value instanceof expression)) {
            $this->default_value = $this->getModel()->parseExpression($this->default_value, $this->getObject());
        }
        return $this->default_value;
    }

    /**
     * Value or formula to be used if no data for this attribute is explicitly defined
     *
     * This expression will be used when no other value is given (e.g. inputs will be prefilled with this
     * value). You can use attribute aliases, formulas or explicit values (the latter enclosed in quotes!).
     *
     * @uxon-property default_value
     * @uxon-type metamodel:formula|string|number
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultValue()
     */
    public function setDefaultValue($value) : MetaAttributeInterface
    {
        $this->default_value = $value;
        return $this;
    }
    
    public function hasDefaultValue() : bool
    {
        return ! is_null($this->default_value);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getFixedValue()
     */
    public function getFixedValue()
    {
        if ($this->hasFixedValue() === true && ! ($this->fixed_value instanceof expression)) {
            $this->fixed_value = $this->getModel()->parseExpression($this->fixed_value, $this->getObject());
        }
        return $this->fixed_value;
    }

    /**
     * A formula to calculate the value EVERY TIME this attribute is written (makes it impossible to change the attribute manually)
     *
     * This expression will always be evaluated, when the object is saved - eventually overwriting user
     * input. This is handy for attributes like `last_update_time`, where a fixed value `=Now()` will
     * automatically set the attribute to the time of saving.
     *
     * @uxon-property fixed_value
     * @uxon-type metamodel:formula
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setFixedValue()
     */
    public function setFixedValue($value) : MetaAttributeInterface
    {
        $this->fixed_value = $value;
        return $this;
    }
    
    public function hasFixedValue() : bool
    {
        return ! is_null($this->fixed_value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getFallbackValue()
     */
    public function getFallbackValue()
    {
        if ($this->hasFixedValue()) {
            return $this->getFallbackValue();
        }
        return $this->getDefaultValue();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::hasFallbackValue()
     */
    public function hasFallbackValue() : bool
    {
        return $this->hasDefaultValue() || $this->hasFixedValue();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultSorterDir()
     */
    public function getDefaultSorterDir()
    {
        return $this->default_sorter_dir ? $this->default_sorter_dir : $this->getDataType()->getDefaultSortingDirection();
    }

    /**
     * Direction of default soring if this attribute is one of the default sorting attributes of the object.
     * 
     * @uxon-property default_sorter_dir
     * @uxon-type [ASC,DESC]
     * @uxon-template ASC
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultSorterDir()
     */
    public function setDefaultSorterDir($value)
    {        
        if ($value instanceof SortingDirectionsDataType){
            // everything is OK
        } elseif (SortingDirectionsDataType::isValidStaticValue(strtoupper($value))){
            $value = DataTypeFactory::createFromPrototype($this->getWorkbench(), SortingDirectionsDataType::class)->withValue(strtoupper($value));
        } else {
            throw new UnexpectedValueException('Invalid value "' . $value . '" for default sorting direction in attribute "' . $this->getName() . '": use ASC or DESC');
        }
        
        $this->default_sorter_dir = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getObjectId()
     */
    public function getObjectId()
    {
        return $this->getObject()->getId();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getModel()
     */
    public function getModel()
    {
        return $this->getObject()->getModel();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getShortDescription()
     */
    public function getShortDescription()
    {
        return $this->short_description;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setShortDescription()
     */
    public function setShortDescription($value)
    {
        $this->short_description = $value;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getHint()
     */
    public function getHint()
    {
        return ($this->getShortDescription() ? $this->getShortDescription() : $this->getName());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isInherited()
     */
    public function isInherited()
    {
        return $this->inherited_from_object_id !== null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDataAddressProperties()
     */
    public function getDataAddressProperties() : UxonObject
    {
        if (null === $this->data_address_properties) {
            $this->data_address_properties = new UxonObject();
        }
        return $this->data_address_properties;
    }

    /**
     * Custom settings for this attribute in the selected data source of its object.
     *
     * @uxon-property data_address_properties
     * @uxon-type object
     * @uxon-template {"": ""}
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDataAddressProperties()
     */
    public function setDataAddressProperties(UxonObject $value)
    {
        $this->data_address_properties = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDataAddressProperty()
     */
    public function getDataAddressProperty($id)
    {
        return $this->getDataAddressProperties()->getProperty($id);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDataAddressProperty()
     */
    public function setDataAddressProperty($id, $value)
    {
        $this->getDataAddressProperties()->setProperty($id, $value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isLabelForObject()
     */
    public function isLabelForObject()
    {
        return $this->getAlias() === $this->getObject()->getLabelAttributeAlias();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isUidForObject()
     */
    public function isUidForObject()
    {
        return $this->getObject()->getUidAttributeAlias() === $this->getAlias();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isExactly()
     */
    public function isExactly(MetaAttributeInterface $attribute)
    {
        if ($this->getId() == $attribute->getId() && $this->getObject()->isExactly($attribute->getObject())) {
            return true;
        }
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::is()
     */
    public function is(MetaAttributeInterface $attribute)
    {
        // IDEA perhaps it would be better to use $attribute->getAliasWithRelationPath() for comparison?
        if (strcasecmp($this->getAlias(), $attribute->getAlias()) === 0 && $this->getObject()->is($attribute->getObject())) {
            return true;
        }
        return false;
    }

    /**
     * Returns an copy of the attribute.
     * 
     * Copies of attributes with a relation path will get a copy of that path by default. Seting $ignoreRelationPath to TRUE
     * will make the copy not have a relation path at all.
     * 
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     * 
     * @param bool $ignoreRelationPath
     * 
     * @return MetaAttributeInterface
     */
    public function copy(bool $ignoreRelationPath = false) : self
    {
        $copy = clone $this;
        
        // If the relation path is not specified or empty, just remove it. This leads to the creation
        // of a new path object when $copy->getRelationPath() is called, thus making sure relation path objects
        // are not shared between attributes and cannot get out of sync. The same happens if the caller
        // chooses to ignore the relation path explicitly.
        if ($ignoreRelationPath === true || $this->relation_path === null || $this->relation_path->isEmpty()) {
            $copy->relation_path = null;
        } else {
            // If the relation path is not empty and should be kept, copy it too in order to still make
            // sure, instances are not shared.
            $copy->relation_path = $this->relation_path->copy();
        }
        
        // Do not use getDefaultEditorUxon() here as it already performs some enrichment
        if ($this->default_editor_uxon instanceof UxonObject){
            $copy->setDefaultEditorUxon($this->default_editor_uxon->copy());
        }
        
        // Do not use getDefaultDisplayUxon() here as it already performs some enrichment
        if ($this->default_display_uxon instanceof UxonObject){
            $copy->setDefaultDisplayUxon($this->default_display_uxon->copy());
        }
        
        return $copy;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::rebase()
     */
    public function rebase(MetaRelationPathInterface $path) : MetaAttributeInterface
    {
        if ($path->getEndObject() !== $this->getObject()) {
            throw new UnexpectedValueException('Cannot rebase attribute "' . $this->getAlias() . '" of object "' . $this->getObject()->getAliasWithNamespace() . '" relative to "' . $path->toString() . '": the relation path must point to the same object, but points to "' . $path->getEndObject()->getAliasWithNamespace() . '" instead!');
        }
        $copy = $this->copy(true);
        $copy->relation_path = $path;
        return $copy;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isSystem()
     */
    public function isSystem() : bool
    {
        return $this->system;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setSystem()
     */
    public function setSystem(bool $value) : MetaAttributeInterface
    {
        $this->system = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getModel()->getWorkbench();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultAggregateFunction()
     */
    public function getDefaultAggregateFunction()
    {
        return $this->default_aggregate_function;
    }

    /**
     * When aggregating use this aggregator by default for this attribute (if no aggregator is defined explicitly)
     * 
     * @uxon-property default_aggregate_function
     * @uxon-type metamodel:aggregator
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultAggregateFunction()
     */
    public function setDefaultAggregateFunction($value)
    {
        if ($value === '') {
            $value = null;
        }
        $this->default_aggregate_function = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isSortable()
     */
    public function isSortable()
    {
        return $this->sortable ?? false;
    }

    /**
     * Set to TRUE/FALSE to allow/forbid sorting over this attribute
     * 
     * @uxon-property sortable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setSortable()
     */
    public function setSortable($value)
    {
        $this->sortable = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isFilterable()
     */
    public function isFilterable()
    {
        return $this->filterable ?? false;
    }

    /**
     * Set to TRUE/FALSE to allow/forbid filtering over this attribute
     * 
     * @uxon-property filterable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setFilterable()
     */
    public function setFilterable($value)
    {
        $this->filterable = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isAggregatable()
     */
    public function isAggregatable()
    {
        return $this->aggregatable;
    }

    /**
     * Set to TRUE/FALSE to allow/forbid aggregating over this attribute
     * 
     * @uxon-property aggregatable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setAggregatable()
     */
    public function setAggregatable($value)
    {
        $this->aggregatable = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getValueListDelimiter()
     */
    public function getValueListDelimiter()
    {
        return $this->value_list_delimiter;
    }
    
    /**
     * Separator character to use when listing multiple values of this attribute
     * 
     * @uxon-property value_list_delimiter
     * @uxon-type string
     * @uxon-default ,
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setValueListDelimiter()
     */
    public function setValueListDelimiter($string)
    {
        if (!is_null($string) && $string !== ''){
            $this->value_list_delimiter = $string;
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::explodeValueList()
     */
    public function explodeValueList(string $delimitedString) : array
    {
        $array = explode($this->getValueListDelimiter(), $delimitedString);
        array_walk($array, 'trim');
        return $array;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::implodeValueList()
     */
    public function implodeValueList(array $values) : string
    {
        return implode($this->getValueListDelimiter(), $values);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getCustomDataTypeUxon()
     */
    public function getCustomDataTypeUxon()
    {
        if ($this->custom_data_type_uxon === null){
            if ($this->custom_data_type_string !== null) {
                $this->custom_data_type_uxon = UxonObject::fromJson($this->custom_data_type_string);
            } else {
                return new UxonObject();
            }
        }
        return $this->custom_data_type_uxon->copy();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setCustomDataTypeUxon()
     */
    public function setCustomDataTypeUxon($uxonOrString) : MetaAttributeInterface
    {
        if ($uxonOrString instanceof UxonObject) {
            $this->custom_data_type_uxon = $uxonOrString;
            $this->custom_data_type_string = null;
        } elseif (is_string($uxonOrString)) {
            $this->custom_data_type_string = $uxonOrString;
            $this->custom_data_type_uxon = null;
        } else {
            throw new InvalidArgumentException('Invalid custom data type UXON for attribute ' . $this->getAlias() . ' of object ' . $this->getObject()->getAliasWithNamespace() . ': expecting string or UXON object!');
        }
        $this->data_type = null;
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultEditorUxon()
     */
    public function getDefaultEditorUxon()
    {
        $dataType = $this->getDataType();
        // Relations need special default editors - InputComboTable or similar to select the related
        // object rather than edit the value itself. But only for string or numeric data types! 
        // Do not produce relation selectors for dates, booleans, binaries, etc.
        if ($this->isRelation() && ($dataType instanceof NumberDataType || $dataType instanceof StringDataType)){
            $makeRelationSelector = true;
        } else {
            $makeRelationSelector = false;
        }
        // If there is no default widget uxon defined, use the UXON from the data type
        if ($this->default_editor_uxon === null) {
            if ($this->default_editor_uxon_string !== null) {
                $this->default_editor_uxon = UxonObject::fromJson($this->default_editor_uxon_string);
            } else {
                // Relations do not use the data type widget, but rather the special relation widget type from the config,
                // which will be set set later on. Setting it here would not work if a default editor is specified, but
                // no widget_type is set explicitly (why should a user do that if a decent type is selected by default?)
                if ($makeRelationSelector) {
                    $this->default_editor_uxon = new UxonObject();
                } else {
                    $this->default_editor_uxon = $this->getDataType()->getDefaultEditorUxon()->copy();
                }
            }
        }
        
        // If the attribute is a relation and no widget type was specified explicitly, take it from the config!
        if ($makeRelationSelector && ! $this->default_editor_uxon->hasProperty('widget_type')) {
            $this->default_editor_uxon = $this->default_editor_uxon->extend($this->getRelation()->getDefaultEditorUxon());
        }
        
        $uxon = $this->default_editor_uxon->copy();
        
        if (! $uxon->hasProperty('attribute_alias')) {
            $uxon->setProperty('attribute_alias', $this->getAliasWithRelationPath());
        }
        
        return $uxon;
    }
    
    /**
     * A default editor widget for this attribute
     *
     * @uxon-property default_editor_uxon
     * @uxon-type \exface\Core\Widgets\Input
     * @uxon-template {"widget_type": ""}
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultEditorUxon()
     */
    public function setDefaultEditorUxon($uxonOrString) : MetaAttributeInterface
    {
        if ($uxonOrString instanceof UxonObject) {
            $this->default_editor_uxon = $uxonOrString;
            $this->default_editor_uxon_string = null;
        } elseif (is_string($uxonOrString)) {
            $this->default_editor_uxon_string = $uxonOrString;
            $this->default_editor_uxon = null;
        } else {
            throw new InvalidArgumentException('Invalid default editor UXON for attribute ' . $this->getAlias() . ' of object ' . $this->getObject()->getAliasWithNamespace() . ': expecting string or UXON object!');
        }
        return $this;
    }

    /**
     * Default editor widget to use for this attribute
     * 
     * @uxon-property default_editor_widget
     * @uxon-type \exface\Core\Widgets\Input
     * @uxon-template {"widget_type": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return MetaAttributeInterface
     */
    protected function setDefaultEditorWidget(UxonObject $uxon) : MetaAttributeInterface
    {
        return $this->setDefaultEditorUxon($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getDefaultDisplayUxon()
     */
    public function getDefaultDisplayUxon() : UxonObject
    {
        // If there is no default widget uxon defined, use the UXON from the data type
        if ($this->default_display_uxon === null) {
            if ($this->default_display_uxon_string !== null) {
                $this->default_display_uxon = UxonObject::fromJson($this->default_display_uxon_string);
            } else {
                $tpl = $this->getDataType()->getDefaultDisplayUxon()->copy();
                if ($tpl->isEmpty()) {
                    $tpl->setProperty('widget_type', 'Display');
                }
                $this->default_display_uxon = $tpl;
            }
        }
        
        $uxon = $this->default_display_uxon->copy();
        
        // Set the attribute alias AFTER copying the UXON because the UXON object may
        // be inherited from or by other attributes and we do not want to modify it 
        // directly
        if (! $uxon->hasProperty('attribute_alias')) {
            $uxon->setProperty('attribute_alias', $this->getAliasWithRelationPath());
        }
        
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setDefaultDisplayUxon()
     */
    public function setDefaultDisplayUxon($uxonOrString) : MetaAttributeInterface
    {
        if ($uxonOrString instanceof UxonObject) {
            $this->default_display_uxon = $uxonOrString;
            $this->default_display_uxon_string = null;
        } elseif (is_string($uxonOrString)) {
            $this->default_display_uxon_string = $uxonOrString;
            $this->default_display_uxon = null;
        } else {
            throw new InvalidArgumentException('Invalid default display UXON for attribute ' . $this->getAlias() . ' of object ' . $this->getObject()->getAliasWithNamespace() . ': expecting string or UXON object!');
        }
        return $this;
    }

    /**
     * Default display widget to use for this attribute
     * 
     * @uxon-property default_display_widget
     * @uxon-type \exface\Core\Widgets\Display
     * @uxon-template {"widget_type": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return MetaAttributeInterface
     */
    protected function setDefaultDisplayWidget(UxonObject $uxon) : MetaAttributeInterface
    {
        return $this->setDefaultDisplayUxon($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isRelated()
     */
    public function isRelated() : bool
    {
        return $this->getRelationPath()->isEmpty() === false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::isCopyable()
     */
    public function isCopyable(): bool
    {
        return $this->copyable;
    }

    /**
     * Set to FALSE to skip this attribute when copying an instance of its objects
     * 
     * @uxon-property copyable
     * @uxon-type boolean
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setCopyable()
     */
    public function setCopyable(bool $value): MetaAttributeInterface
    {
        $this->copyable = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::__toString()
     */
    public function __toString() : string
    {
        return '"' . $this->getName() . '" (alias "' . $this->getAliasWithRelationPath() . '")';
    }

    public function addGroupSelector(AttributeGroupSelectorInterface $selector) : MetaAttributeInterface
    {
        $this->groupSelectors[$selector->toString()] = $selector;
        return $this;
    }

    /**
     * A list of group aliases, this attribute belongs to.
     * 
     * @uxon-property groups
     * @uxon-type metamodel:attribute_group[]
     * @uxon-template [""]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return Attribute
     */
    protected function setGroups(UxonObject $uxon) : MetaAttributeInterface
    {
        $obj = $this->getObject();
        foreach ($uxon as $alias) {
            $obj->getAttributeGroup($alias)->add($this);
        }
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::setType()
     */
    public function setType(string $attrType) : MetaAttributeInterface
    {
        try {
            $this->attributeType = MetaAttributeTypeDataType::cast($attrType);
        } catch (Throwable $e) {
            throw new MetaObjectModelError($this->getObject(), 'Invalid attribute type "' . $attrType . '" provided for ' . $this->__toString());
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getType()
     */
    public function getType() : string
    {
        return $this->attributeType;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaAttributeInterface::getOrigin()
     */
    public function getOrigin() : int
    {
        return $this->isInherited() ? MetaAttributeOriginDataType::INHERITED_ATTRIBUTE : MetaAttributeOriginDataType::DIRECT_ATTRIBUTE;
    }

    /**
     * {@inheritDoc}
     * @see iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'alias' => $this->getAliasWithRelationPath(),
            'name' => $this->getName(),
            'data_address' => $this->getDataAddress()
        ]);

        // TODO add other UXON properties here

        return $uxon;
    }
}