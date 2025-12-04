<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;

interface iHaveColumns extends WidgetInterface
{
    /**
     * 
     * @param DataColumn $column
     * @param integer|null $position
     * @return iHaveColumns
     */
    public function addColumn(DataColumn $column, int $position = NULL) : iHaveColumns;

    /**
     * 
     * @return DataColumn[]
     */
    public function getColumns() : array;

    /**
     * 
     * @param UxonObject $columns
     * @return iHaveColumns
     */
    public function setColumns(UxonObject $columns) : iHaveColumns;
    
    /**
     * Returns TRUE if the widget has at least one column at the moment and FALSE otherwise.
     *
     * @return boolean
     */
    public function hasColumns() : bool;
    
    /**
     *
     * @param string $widgetId
     * @return DataColumn|NULL
     */
    public function getColumn(string $widgetId) : ?DataColumn;
    
    /**
     * Returns the first column with a matching attribute alias.
     *
     * @param string $alias_with_relation_path
     * @return DataColumn|NULL
     */
    public function getColumnByAttributeAlias(string $alias_with_relation_path) : ?DataColumn;
    
    /**
     *
     * @param string $data_sheet_column_name
     * @return DataColumn|NULL
     */
    public function getColumnByDataColumnName(string $data_sheet_column_name) : ?DataColumn;
    
    /**
     * 
     * @param ExpressionInterface|string $expressionOrString
     * @return DataColumn|NULL
     */
    public function getColumnByExpression($expressionOrString) : ?DataColumn;
    
    /**
     * 
     * @param DataColumn $column
     * @return iHaveColumns
     */
    public function removeColumn(DataColumn $column) : iHaveColumns;
    
    /**
     * @return string
     */
    public function getColumnDefaultWidgetType() : string;
    
    public function countColumns() : int;
    
    /**
     * 
     * @param MetaAttributeInterface $attribute
     * @param string $caption
     * @param bool|null $hidden
     * @param bool $editable
     * @return DataColumn
     */
    public function createColumnFromAttribute(MetaAttributeInterface $attribute, string $caption = null, bool $hidden = null, bool $editable = false) : DataColumn;
    
    /**
     * The column is not automatically added to the column group - use addColumn() explicitly!
     *
     * @param UxonObject $uxon
     * @return DataColumn
     */
    public function createColumnFromUxon(UxonObject $uxon) : DataColumn;
    
    /**
     * Returns the UID column as DataColumn
     *
     * @return DataColumn|null
     */
    public function getUidColumn() : ?DataColumn;
    
    /**
     * Returns TRUE if this data widget has a UID column or FALSE otherwise.
     *
     * @return boolean
     */
    public function hasUidColumn() : bool;
}