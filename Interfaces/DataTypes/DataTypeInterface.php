<?php
namespace exface\Core\Interfaces\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\Model\MetaModelPrototypeInterface;
use exface\Core\Interfaces\ValueObjectInterface;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataTypeInterface extends WorkbenchDependantInterface, AliasInterface, iCanBeCopied, iCanBeConvertedToUxon, MetaModelPrototypeInterface, ValueObjectInterface
{
    /**
     * Returns the string name of the data type (e.g.
     * Number, String, etc.)
     *
     * @return string
     */
    public function getName() : string;

    /**
     * Returns TRUE if the current data type equals or is derived from the given one.
     * 
     * Example: concider prototypes Integer extends Number with respective models and
     * model-defined types NumericId (based on Integer prototype), PositiveNumber and
     * NegativeNumber (based on Number). 
     * 
     * - Number::is(Integer) => false
     * - NumericId::is(Integer) => true
     * - NumericId::is(Number) => true
     * - NumericId::is(PositiveNumber) => false
     * - Integer::is('exface.Core.Number') => true // both are prototypes and Integer is subclass of Number.
     * - Integer::is(Number) => true
     * - Integer::is(NumericId) => false
     * - PositiveNumber::is(Number) => true
     * - PositiveNumber::is(NegativeNuber) => false
     * - PositiveNumber::is(NumericId) => false
     * - Number::is(Number) => true
     *
     * @param DataTypeInterface|string $data_type_or_resolvable_name
     * @return boolean
     */
    public function is($data_type_or_resolvable_name) : bool;

    /**
     * Returns TRUE if this data type matches the given one (i.e. their qualified aliases match exactly) 
     * and FALSE otherwise.
     * 
     * @param DataTypeInterface|string $data_type_or_resolvable_name
     * @return bool
     */
    public function isExactly($data_type_or_resolvable_name) : bool;

    /**
     * Returns a normalized representation of the given string matching the data prototype, but 
     * does not check any configurable resrictions of the data type instance.
     * 
     * In other words, the string is made data prototype conform. That's all we can do without
     * instantiating a concrete data type. On the other hand, any valid value of any data type
     * based on this prototype will pass casting without being modified.
     * 
     * E.g. DateDataType::cast('21.9.1984') = 1984-09-21.
     * 
     * Note, that cast() does not normalize empty values: for most data types NULL and '' (empty
     * string) are concidered empty values, but they are both valid from the point of view of
     * cast() - so `cast(null) === null` and `cast('') === ''`. This is done intentially because
     * an empty string and NULL are actually different values an sometimes need to be treated
     * differently in data sources.
     * 
     * @see DataTypeInterface::parse($string) for a similar, but more restrictive method for 
     * instantiated types.
     * @see DataTypeInterface::isValueEmpty($string) for a type-specific check for empty values.
     *
     * @param mixed $value
     *        
     * @throws DataTypeCastingError
     * 
     * @return mixed
     */
    public static function cast($value);
    
    /**
     * Returns true if the given value is empty (i.e. cast() will return NULL) and FALSE otherwise
     * 
     * @param mixed $value
     * @return bool
     */
    public static function isValueEmpty($value) : bool;
    
    /**
     * Returns TRUE if the given value is a valid value meaning NULL.
     * 
     * In contrast to isValueEmpty() this value is set and a valid one, but it's meaning
     * is NULL or EMPTY.
     * 
     * @param mixed $value
     * @return bool
     */
    public static function isValueLogicalNull($value) : bool;
    
    /**
     * Returns a normalized representation of the given string mathing all the rules defined in the
     * data type.
     * 
     * While the static cast() method only makes the value compatible with the prototype, parse()
     * will make sure it matches all rules of the data type - including those defined in it's model.
     * 
     * E.g. NumberDataType::cast(1,5523) = 1.5523, but exface.Core.NumberNatural->parse(1,5523) = 1,
     * because the natural number model not only casts anything to a number, but also rounds it to
     * the a whole number.
     *
     * @param mixed $value
     * 
     * @throws DataTypeValidationError
     * 
     * @return mixed
     */
    public function parse($value);
    
    /**
     * Returns the unique error code (error model alias) used for parsing errors of this data type.
     * 
     * @return string|NULL
     */
    public function getValidationErrorCode() : ?string;
    
    /**
     * Sets the unique error code (error model alias) used for parsing errors of this data type.
     * 
     * @param string $string
     * @return DataTypeInterface
     */
    public function setValidationErrorCode(string $string) : DataTypeInterface;
    
    /**
     * Returns the text explaining validation errors (e.g. "Model entity aliases must not start with '_' or '~').
     * 
     * @return string|NULL
     */
    public function getValidationErrorText() : ?string;
    
    
    /**
     * Changes the explanation text for validation errors.
     * 
     * @param string $string
     * @return DataTypeInterface
     */
    public function setValidationErrorText(string $string) : DataTypeInterface;

    /**
     * Returns TRUE if the given value matches the data type (and thus can be parsed) or FALSE otherwise.
     *
     * @param mixed $string            
     * @return boolean
     */
    public function isValidValue($value) : bool;
    
    /**
     * 
     * @return SortingDirectionsDataType
     */
    public function getDefaultSortingDirection();
    
    /**
     * Returns the app, to which this data type belongs to.
     * 
     * NOTE: if the model of this data type belongs to another app, than its prototype, this method
     * will return the app of the model. 
     * 
     * @return AppInterface
     */
    public function getApp() : AppInterface;
    
    /**
     * @return string|NULL
     */
    public function getShortDescription() : ?string;
    
    /**
     * 
     * @param string $text
     * @return DataTypeInterface
     */
    public function setShortDescription(string $text) : DataTypeInterface;
    
    /**
     * 
     * @param UxonObject $uxon
     * @return DataTypeInterface
     */
    public function setDefaultEditorUxon(UxonObject $uxon) : DataTypeInterface;
    
    /**
     * 
     * @param UxonObject $uxon
     * @return DataTypeInterface
     */
    public function setDefaultEditorWidget(UxonObject $uxon) : DataTypeInterface;
    
    /**
     * @return UxonObject
     */
    public function getDefaultEditorUxon() : UxonObject;
    
    /**
     * 
     * @return DataTypeSelectorInterface
     */
    public function getSelector() : DataTypeSelectorInterface;
    
    /**
     * Returns a translated hint describing supported input formats for this data type.
     * 
     * This hint will be displayed in the autogenerated help dialogs. If a data type
     * accepts special input syntax (e.g. +1d for dates or 1k for numbers), this hint
     * should explain this syntax briefly to the user. The hint can contain one or more
     * lines, but must not contain formatting like HTML.
     * 
     * @return string
     */
    public function getInputFormatHint() : string;
    
    /**
     * Returns TRUE if the data type represents sensitive data (e.g. passwords, secrets, etc.)
     * 
     * This makes sure the sensitive data is not shown as plain text!
     * 
     * @return bool
     */
    public function isSensitiveData() : bool;
    
    /**
     * Set if the data is sensitive, so it can be censored if needed, for example passwords 
     * in data sheet exceptions.
     *
     * @param bool $trueOrFalse
     * @return DataTypeInterface
     */
    public function setSensitiveData(bool $trueOrFalse) : DataTypeInterface;
}
?>