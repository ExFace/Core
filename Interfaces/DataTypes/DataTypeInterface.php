<?php
namespace exface\Core\Interfaces\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\CommonLogic\Model\Model;
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
     * @param string $string
     * @return DataTypeInterface
     */
    public function setAlias($string);
    
    /**
     *
     * @return Model
     */
    public function getModel();

    /**
     * Returns the string name of the data type (e.g.
     * Number, String, etc.)
     *
     * @return string
     */
    public function getName();
    
    /**
     * @return DataTypeInterface
     */
    public function setName($string);

    /**
     * Returns TRUE if the current data type equals is derived from the given one (e.g.
     * Integer::is('exface.Core.Number') = true) and FALSE otherwise.
     *
     * @param DataTypeInterface|string $data_type_or_resolvable_name
     * @return boolean
     */
    public function is($data_type_or_resolvable_name);

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
     * @see DataTypeInterface::isEmptyValue($string) for a type-specific check for empty values.
     *
     * @param mixed $string    
     * @param mixed $emptyValue        
     * @throws DataTypeCastingError
     * @return string
     */
    public static function cast($string);
    
    /**
     * Returns true if the given value is empty (i.e. cast() will return NULL) and FALSE otherwise
     * 
     * @param mixed $string
     * @return bool
     */
    public static function isEmptyValue($string) : bool;
    
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
     * @param mixed $string
     * @throws DataTypeValidationError
     * @return string
     */
    public function parse($string);
    
    /**
     * Returns the unique error code (error model alias) used for parsing errors of this data type.
     * 
     * @return string
     */
    public function getValidationErrorCode();
    
    /**
     * Sets the unique error code (error model alias) used for parsing errors of this data type.
     * 
     * @param string $string
     * @return DataTypeInterface
     */
    public function setValidationErrorCode($string);
    
    /**
     * Returns the text explaining validation errors (e.g. "Model entity aliases must not start with '_' or '~').
     * 
     * @return string
     */
    public function getValidationErrorText();
    
    
    /**
     * Changes the explanation text for validation errors.
     * 
     * @param string $string
     * @return DataTypeInterface
     */
    public function setValidationErrorText($string);

    /**
     * Returns TRUE if the given value matches the data type (and thus can be parsed) or FALSE otherwise.
     *
     * @param mixed $string            
     * @return boolean
     */
    public function isValidValue($string);
    
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
    public function getApp();
    
    /**
     * 
     * @param AppInterface $app
     * @return DataTypeInterface
     */
    public function setApp(AppInterface $app);
    
    /**
     * @return string
     */
    public function getShortDescription();
    
    /**
     * 
     * @param string $text
     * @return DataTypeInterface
     */
    public function setShortDescription($text);
    
    /**
     * 
     * @param UxonObject $uxon
     * @return DataTypeInterface
     */
    public function setDefaultEditorUxon(UxonObject $uxon);
    
    /**
     * 
     * @param UxonObject $uxon
     * @return DataTypeInterface
     */
    public function setDefaultEditorWidget(UxonObject $uxon);
    
    /**
     * @return UxonObject
     */
    public function getDefaultEditorUxon();
    
    /**
     * 
     * @return DataTypeSelectorInterface
     */
    public function getSelector() : DataTypeSelectorInterface;
}
?>