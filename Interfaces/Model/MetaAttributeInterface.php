<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\DataTypes\SortingDirectionsDataType;

interface MetaAttributeInterface extends ExfaceClassInterface, iCanBeCopied
{
    public function __construct(MetaObjectInterface $object);
    
    /**
     * Marks this attribute as a relation
     *
     * @param boolean $value
     */
    public function setRelationFlag($value);
    
    /**
     * Returns TRUE if this attribute actually is a relation and FALSE otherwise.
     * The relation itself can be obtained by calling get_relation().
     *
     * @see getRelation()
     * @return boolean
     */
    public function isRelation();
    
    /**
     * Returns the relation, this attribute represents if it is a relation attribute and NULL otherwise
     *
     * @return MetaRelationInterface
     */
    public function getRelation();
    
    public function getAliasWithRelationPath();
    
    public function getId();
    
    public function setId($value);
    
    public function getAlias();
    
    public function setAlias($value);
    
    /**
     * Returns the data type of the attribute as an instantiated data type object
     *
     * @return DataTypeInterface
     */
    public function getDataType();
    
    /**
     *
     * @param string|DataTypeInterface $object_or_name
     * @throws UnexpectedValueException
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function setDataType($instance_or_resolvable_string);
    
    public function getDefaultDisplayOrder();
    
    public function setDefaultDisplayOrder($value);
    
    /**
     * Returns TRUE if values for this attribute can be read from the data 
     * source of it's object and FALSE otherwise.
     * 
     * This is not allways the case, as attributes can be calculated or
     * even only used for filtering sorting and cannot be "selected"
     * directly.
     * 
     * @return boolean
     */
    public function isReadable();
    
    /**
     * Marks the attribute as redable (TRUE) or not (FALSE).
     * 
     * @param boolean $true_or_false
     * @return MetaAttributeInterface
     */
    public function setReadable($true_or_false);
    
    /**
     * Returns TRUE if values of this attribute can be written to the data source
     * of its object or FALSE otherwise.
     * 
     * @return boolean
     */
    public function isWritable();
    
    /**
     * Marks the attribute as writable (TRUE) or not (FALSE).
     * 
     * @param boolean $true_or_false
     * @return MetaAttributeInterface
     */
    public function setWritable($true_or_false);
    
    /**
     * Returns TRUE if the attribute can be changed and FALSE if it is read only.
     * Attributes of objects from read-only data sources are never editable!
     *
     * @return boolean
     */
    public function isEditable();
    
    public function setEditable($value);
    
    /**
     *
     * @return unknown
     */
    public function getFormatter();
    
    /**
     *
     * @param unknown $value
     */
    public function setFormatter($value);
    
    public function isHidden();
    
    public function setHidden($value);
    
    public function getName();
    
    public function setName($value);
    
    public function isRequired();
    
    public function setRequired($value);
    
    public function getDataAddress();
    
    public function setDataAddress($value);
    
    /**
     * Returns the relation path for this attribute, no matter how deep
     * the relation is.
     * E.g. calling it for the attribute PRICE of POSITION__PRODUCT
     * (POSITION__PRODUCT__PRICE) would result in POSITION__PRODUCT as
     * path.
     * Returns NULL if the attribute belongs to the object itself.
     *
     * @return MetaRelationPathInterface
     */
    public function getRelationPath();
    
    /**
     * Returns the meta object to which this attributes belongs to.
     * If the attribute has a relation path, this
     * will return the last object in that path.
     *
     * If the attribute is inherited, the inheriting object will be returned. To get the base object, the
     * attribute was inherited from, use getObjectInheritedFrom().
     *
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getObject();
    
    /**
     * Returns the object, this attribute was inherited from.
     * 
     * If the attribute was not inherited this returns it's regular object (same as get_object()).
     *
     * If the attribute was inherited multiple times, this method will go back exactly one step. For example, if we have a base object
     * of a data source, that is extended by OBJECT1, which in turn, is extended by OBJECT2, calling get_object_extended_from() on an
     * attribute of OBJECT2 will return OBJECT1, while doing so for OBJECT1 will return the base object.
     *
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getObjectInheritedFrom();
    
    /**
     * Returns a copy of the custom UXON configuration for the attribute's data type.
     * 
     * To change the data type configuration either use $this->getDataType() directly or setCustomDataTypeUxon().
     * 
     * @return UxonObject
     */
    public function getCustomDataTypeUxon();
    
    /**
     * @param UxonObject $uxon
     * @return MetaAttributeInterface
     */
    public function setCustomDataTypeUxon(UxonObject $uxon);
    
    public function getFormula();
    
    public function setFormula($value);
    
    /**
     * Returns an expression for the default value of this attribute, which is to be used, when saving the attribute without an explicit value given in the data sheet.
     *
     * @see getFixedValue() in contrast to the fixed value, the default value is always overridden by any value in the data sheet.
     * @return \exface\Core\Interfaces\Model\ExpressionInterface
     */
    public function getDefaultValue();
    
    public function setDefaultValue($value);
    
    /**
     * Returns TRUE if the attribute has a defaultvalue and FALSE otherwise.
     * 
     * @return bool
     */
    public function hasDefaultValue() : bool;
    
    /**
     * Returns an expression for value of this attribute, which is to be set 
     * or updated every time the attribute is saved to the data source.
     *
     * @return \exface\Core\Interfaces\Model\ExpressionInterface
     */
    public function getFixedValue();
    
    /**
     * 
     * @param ExpressionInterface|string $value
     */
    public function setFixedValue($value);
    
    /**
     * Returns TRUE if the attribute has a fixed value and FALSE otherwise.
     * @return bool
     */
    public function hasFixedValue() : bool;
    
    /**
     * Returns the fallback value of the attribute to use in a data sheet if not 
     * value is specified explicitly: the fixed value if set, otherwise the defualt
     * value.
     * 
     * Returns null if no fallback existis (neither fixed nor default values).
     *
     * @return ExpressionInterface|null
     */
    public function getFallbackValue();
    
    /**
     * Returns TRUE if the attribute has a fallback value: a default or a fixed value.
     * 
     * @return bool
     */
    public function hasFallbackValue() : bool;
    
    /**
     *
     * @return \exface\Core\DataTypes\SortingDirectionsDataType
     */
    public function getDefaultSorterDir();
    
    /**
     *
     * @param SortingDirectionsDataType|string $value
     */
    public function setDefaultSorterDir($value);
    
    public function getObjectId();
    
    public function getModel();
    
    public function getShortDescription();
    
    public function setShortDescription($value);
    
    public function getHint();
    
    /**
     * Returns the UID of the object, this attribute was inherited from or NULL if it is a direct attribute of it's object
     *
     * @return string
     */
    public function getInheritedFromObjectId();
    
    /**
     *
     * @param string $value
     */
    public function setInheritedFromObjectId($value);
    /**
     * Returns TRUE if this Relation was inherited from a parent object
     *
     * @return boolean
     */
    public function isInherited();
    
    /**
     *
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function getDataAddressProperties();
    
    /**
     *
     * @param UxonObject $value
     * @return MetaAttributeInterface
     */
    public function setDataAddressProperties(UxonObject $value);
    
    /**
     * Returns the value of a data source specifi object property specified by it's id
     *
     * @param string $id
     */
    public function getDataAddressProperty($id);
    
    /**
     *
     * @param string $id
     * @param mixed $value
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function setDataAddressProperty($id, $value);
    
    /**
     * Returns TRUE if the attribute is used as the label for it's object or FALSE otherwise
     *
     * @return boolean
     */
    public function isLabelForObject();
    
    /**
     * Returns TRUE if this attribute is used as UID for it's object and FALSE otherwise
     *
     * @return boolean
     */
    public function isUidForObject();
    
    /**
     * Returns TRUE if this attribute is the same (same UID, same object), as the given attribute, and FALSE otherwise.
     *
     * This method will also return TRUE if the attributes have differen relations paths.
     * NOTE: comparing the UID is not enough, as inherited attributes will keep their UID.
     *
     * @param MetaAttributeInterface $attribute
     * @return boolean
     */
    public function isExactly(MetaAttributeInterface $attribute);
    
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
     * @param MetaAttributeInterface $attribute
     * @return boolean
     */
    public function is(MetaAttributeInterface $attribute);
    
    /**
     * Creates a copy of the attribute relative to a given relation path.
     * This is usefull if you want to rebase an attribute.
     *
     * @param MetaRelationPathInterface $path
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function rebase(MetaRelationPathInterface $path);
    
    /**
     * Returns TRUE if this attribute is a system attribute.
     * System attributes are required by the internal logic
     * (like the UID attribute) an will be loaded by default in all data sheets
     *
     * @return boolean
     */
    public function isSystem();
    
    /**
     * Marks the attribute as system (TRUE) or non-system (FALSE).
     * System attributes are required by the internal logic (like the UID attribute) an will be loaded by default
     * in all data sheets
     *
     * @param boolean $value
     * @return MetaAttributeInterface
     */
    public function setSystem($value);
    
    /**
     *
     * @return string
     */
    public function getDefaultAggregateFunction();
    
    /**
     *
     * @param string $value
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function setDefaultAggregateFunction($value);
    
    /**
     *
     * @return boolean
     */
    public function isSortable();
    
    /**
     *
     * @param boolean $value
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function setSortable($value);
    
    /**
     *
     * @return boolean
     */
    public function isFilterable();
    
    /**
     *
     * @param boolean $value
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function setFilterable($value);
    
    /**
     *
     * @return boolean
     */
    public function isAggregatable();
    
    /**
     *
     * @param boolean $value
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function setAggregatable($value);
    
    /**
     * Returns the delimiter to be used when concatennating multiple values of
     * this attribute into a string.
     *
     * Defaults to EXF_LIST_SEPARATOR unless changed via setValueListDelimiter()
     *
     * @return string
     */
    public function getValueListDelimiter();
    
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
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function setValueListDelimiter($string);
    
    
    
    /**
     * Returns a copy of the UXON object for the default editor widget for this attribute.
     *
     * The default editor is defined for a data type and can be overridden for specific attributes. 
     * The attribute-specific default editor will completely replace the one on the data-type-level,
     * the UXONs will not be merged in order to avoid attributes incompatible with the specified
     * widget type.
     *
     * Note: The returned UXON is a copy. Changes on it will not affect the result of the next method call. 
     * If you need to change the default UXON use 
     * MetaAttributeInterface::setDefaultEditorUxon(MetaAttributeInterface::getDefaultEditorUxon()) 
     * or similar.
     *
     * @return UxonObject
     */
    public function getDefaultEditorUxon();
    
    /**
     *
     * @param UxonObject $uxon_object
     */
    public function setDefaultEditorUxon(UxonObject $uxon_object);
}