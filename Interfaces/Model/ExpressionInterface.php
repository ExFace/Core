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
     * 
     * @param \exface\Core\CommonLogic\Workbench $exface
     * @param string $string
     * @param MetaObjectInterface $meta_object
     */
    function __construct(\exface\Core\CommonLogic\Workbench $exface, $string, MetaObjectInterface $meta_object = null);
    
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
    public function evaluate(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $column_name, $row_number = null);
    
    public function getRequiredAttributes();
    
    /**
     * @return string
     */
    public function getType();
    
    public function getRelationPath();
    
    /**
     * Returns a copy of the expression with the relation path replaced by the given one.
     * 
     * @param MetaRelationPathInterface $path
     * @return ExpressionInterface
     */
    public function withRelationPath(MetaRelationPathInterface $path) : ExpressionInterface;
    
    /**
     * Returns the expression as string.
     * Basically this is the opposite fo parse.
     * Note, that in case of attributes the expression will include the relation path, aggregators, etc., whereas getAttribute->getAlias() would return only the actual alias.
     *
     * @return string
     */
    public function toString();
    
    public function getRawValue();
    
    /**
     * Returns the data type, that the calculation result of this expression will have.
     * 
     * @return DataTypeInterface
     */
    public function getDataType() : DataTypeInterface;
    
    public function setDataType($value);
    
    public function mapAttribute($map_from, $map_to);
    
    public function getMetaObject();
    
    public function setMetaObject(MetaObjectInterface $object) : ExpressionInterface;
    
    /**
     * Returns a copy of the expression relative to another base object.
     * E.g. "ORDER->POSITION->PRODUCT->ID" will become "PRODUCT->ID" after calling rebase(ORDER->POSITION) on it.
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
     * Returns true if a string contains a formula, false otherwise.
     *
     * @param mixed $value
     * @return boolean
     */
    public static function detectFormula($value) : bool;
    
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

