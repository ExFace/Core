<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface ExpressionInterface extends ExfaceClassInterface, iCanBeCopied
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
    public function isMetaAttribute();
    
    /**
     * @return boolean
     */
    public function isFormula();
    
    /**
     * @return boolean
     */
    public function isString();
    
    /**
     * Returns TRUE if the expression has no value (expression->toString() = NULL) and FALSE otherwise
     *
     * @return boolean
     */
    public function isEmpty();
    
    /**
     * @return boolean
     */
    public function isReference();
    
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
     * 
     * @param string $relation_path
     */
    public function setRelationPath($relation_path);
    
    /**
     * Returns the expression as string.
     * Basically this is the opposite fo parse.
     * Note, that in case of attributes the expression will include the relation path, aggregators, etc., whereas getAttribute->getAlias() would return only the actual alias.
     *
     * @return string
     */
    public function toString();
    
    public function getRawValue();
    
    public function getDataType();
    
    public function setDataType($value);
    
    public function mapAttribute($map_from, $map_to);
    
    public function getMetaObject();
    
    public function setMetaObject(MetaObjectInterface $object);
    
    /**
     * Returns the same expression, but relative to another base object.
     * E.g. "ORDER->POSITION->PRODUCT->ID" will become "PRODUCT->ID" after calling rebase(ORDER->POSITION) on it.
     *
     * @param string $relation_path_to_new_base_object
     * @return ExpressionInterfaceInterface
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
    public function getWidgetLink();
}

