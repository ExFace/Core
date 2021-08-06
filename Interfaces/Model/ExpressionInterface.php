<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * A UXON expression can be used as a value for a non-static property and can be a number, a
 * (quoted) string, a formula, an attribute alias or a widget link - this interface describes
 * the value object for UXON expressions.
 * 
 * Every expression can be calculated into a single value. Based on calulations requirements,
 * expressions are categorized in:
 * - Static expressions can be calculated as-is, without any context like widgets or data)
 * - Dynamic expressions need data or widget context to get calculated
 * 
 * The main purpose of the interface is to provide methods to find out, which type of expression
 * it is, wether it is static, which data type the calculation results in, etc. 
 * 
 * @author Andrej Kabachnik
 *
 */
interface ExpressionInterface extends WorkbenchDependantInterface, iCanBeCopied
{
    /**
     * @return boolean
     */
    public function isMetaAttribute() : bool;
    
    /**
     * @return boolean
     */
    public function isFormula() : bool;
    
    /**
     * @return boolean
     */
    public function isConstant() : bool;
    
    
    public function isString() : bool;
    
    public function isNumber() : bool;
    
    /**
     * Returns TRUE if the expression can be evaluated without a data context and FALSE otherwise: 
     * i.e. the expression ist static if it does not depend on the contents of data sheets.
     * 
     * @return bool
     */
    public function isStatic() : bool;
    
    /**
     * Returns TRUE if the expression has no value (expression->toString() = NULL) and FALSE otherwise
     *
     * @return boolean
     */
    public function isEmpty() : bool;
    
    /**
     * 
     * @return bool
     */
    public function isLogicalNull() : bool;
    
    /**
     * @return boolean
     */
    public function isReference() : bool;
    
    /**
     * Evaluates the given expression based on a data sheet and the coordinates of a cell.
     * Returns either a string value (if column and row are specified) or an array of values (if only the column is specified).
     *
     * @param DataSheetInterface $data_sheet
     * @param string $column_name
     * @param int $row_number
     * @return array|string
     */
    public function evaluate(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet = null, $row_number = null);
    
    /**
     * Returns an array with aliases of all attributes required for this expression relative to its meta object.
     * 
     * @return string[]
     */
    public function getRequiredAttributes();
    
    /**
     * @return string
     */
    public function getType();
    
    public function getRelationPath();
    
    /**
     * Returns a copy of this expression with the relation path replaced by the given one.
     * 
     * NOTE: In contrast to `rebase()` this method will not change the expression itself - it
     * will only change it's meta object and make it resolve using a relation path from that
     * new object. Calling `withRelationPath()` again will replace the previous relation path
     * and object and make **the same** expression resolve relatively to that new paths starting
     * object. Thus, calling `withRelationPath()` multiple times produces the same result regardless
     * of the sequence, whereas calling `rebase()` multiple will apply changes to the expression
     * in a chained manner.
     * 
     * Examples: 
     * 
     * 1. Assume a simple attribute expression `lat` of some `location` object with attributes for
     * latitude and longitude. To be able to resolve the expression against factory data we could
     * call `withRelationPath('location')`, which would not change the expression itself, but only
     * its meta object. When being `evalute()`ed against factory data the expression would map to
     * `location__lat`. However, we could also use `rebase('factory')` to swith to the factory object,
     * which would actually change the expression to `location__lat`. This method is much more complex
     * and may produce unexpected results, but it can also handle more complex situations! 
     * 2. Assume, the expression `=Concatenate(lat, ',', lng)` is a formula based on our location-object 
     * having latitude and longitude attributes. We can now change the object to `factory` by calling 
     * `withRelationPath('location')` if the factory has a relation called `location`. This will not 
     * change our expression itself, but when being evaluated against factory data it will use 
     * `location__lat` and `location__lng` for arguments. If we need to evaluate the formula against
     * the data of a production order, we might call `withRelationPath('factory__location')` to
     * change the object again and the `lat` argument would be mapped to `factory__location__lat` in
     * our data.
     * 
     * @param MetaRelationPathInterface $relation_path
     * @return ExpressionInterface
     */
    public function withRelationPath(MetaRelationPathInterface $path) : ExpressionInterface;
    
    /**
     * Returns the expression as string.
     * 
     * Basically this is the opposite fo parse.
     * 
     * For quoted strings toString() will return the string including the quotes while
     * evaluate() will remove the quotes.
     * 
     * Note, that in case of attributes the expression will include the relation path, 
     * aggregators, etc., whereas getAttribute()->getAlias() would return only the actual alias.
     * 
     * @see evaluate()
     *
     * @return string|NULL
     */
    public function __toString() : ?string;
    
    public function getRawValue();
    
    /**
     * Returns the data type, that the calculation result of this expression will have.
     * 
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface;
    
    public function setDataType($value);
    
    public function getMetaObject();
    
    public function setMetaObject(MetaObjectInterface $object) : ExpressionInterface;
    
    /**
     * Returns a copy of the expression relative to another base object.
     * 
     * The required argument is the relation from the current base object to the new one. 
     * E.g. `ORDER__POSITION__PRODUCT__ID` will become `PRODUCT__ID` after calling `rebase('ORDER__POSITION')`
     * on it.
     * 
     * NOTE: in contranst to `withRelationPath()` this method will modify the epxression itself instead
     * of merely changing the base object and remembering the relation path from it. Also not the syntax
     * difference: `rebase()` takes the relation path **to** the new object while `withRelationPath()`
     * uses the path **from** the new object. See description of `withRelationPath()` for more details!
     *
     * @param string $relation_path_to_new_base_object
     * @return ExpressionInterface
     */
    public function rebase($relation_path_to_new_base_object);
    
    /**
     * Returns the meta attribute, represented by this expression or FALSE if the expression represents something else (a formula, a constant, etc.)
     *
     * @return MetaAttributeInterface
     */
    public function getAttribute();
    
    /**
     * @return WidgetLinkInterface
     */
    public function getWidgetLink(WidgetInterface $sourceWidget) : WidgetLinkInterface;
    
    /**
     * Returns true if a string seems to contain a calculated value (formula or reference) and false otherwise.
     * 
     * @param mixed $value
     * @return bool
     */
    public static function detectCalculation($value) : bool;
    
    /**
     * Returns true if a string seems to contain a formula and false otherwise.
     *
     * @param mixed $value
     * @return boolean
     */
    public static function detectFormula($value) : bool;
    
    /**
     * Returns true if a string seems to be a reference and false otherwise.
     * 
     * @param mixed $value
     * @return bool
     */
    public static function detectReference($value) : bool;
    
    /**
     * Returns true if a value is a quoted string (is enclosed in " or ') and false otherwise.
     *
     * @param mixed $value
     * @return boolean
     */
    public static function detectQuotedString($value) : bool;
    
    /**
     * Returns true if a value is a number (no quotes and strictly numeric) and false otherwise.
     *
     * @param mixed $value
     * @return boolean
     */
    public static function detectNumber($value) : bool;
}