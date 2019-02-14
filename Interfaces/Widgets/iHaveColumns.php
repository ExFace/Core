<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

interface iHaveColumns extends WidgetInterface
{
    /**
     * 
     * @param \exface\Core\Widgets\DataColumn $column
     * @param integer|null $position
     * @return iHaveColumns
     */
    public function addColumn(\exface\Core\Widgets\DataColumn $column, int $position = NULL) : iHaveColumns;

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
     * @return \exface\Core\Widgets\DataColumn|NULL
     */
    public function getColumn(string $widgetId) : ?DataColumn;
    
    /**
     * Returns the first column with a matching attribute alias.
     *
     * @param string $alias_with_relation_path
     * @return \exface\Core\Widgets\DataColumn|NULL
     */
    public function getColumnByAttributeAlias(string $alias_with_relation_path) : ?DataColumn;
    
    /**
     *
     * @param string $data_sheet_column_name
     * @return \exface\Core\Widgets\DataColumn|NULL
     */
    public function getColumnByDataColumnName(string $data_sheet_column_name) : ?DataColumn;
    
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
     * @param bool $hidden
     * @return DataColumn
     */
    public function createColumnFromAttribute(MetaAttributeInterface $attribute, string $caption = null, bool $hidden = null) : DataColumn;
    
    /**
     * The column is not automatically added to the column group - use addColumn() explicitly!
     *
     * @param UxonObject $uxon
     * @return \exface\Core\Widgets\DataColumn
     */
    public function createColumnFromUxon(UxonObject $uxon) : DataColumn;
}