<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveColumns extends WidgetInterface
{

    /**
     * 
     * @param \exface\Core\Widgets\DataColumn $column
     * @param integer|null $position
     * @return iHaveColumns
     */
    public function addColumn(\exface\Core\Widgets\DataColumn $column, $position = NULL);

    /**
     * 
     * @return DataColumn[]
     */
    public function getColumns();

    /**
     * 
     * @param UxonObject $columns
     * @return iHaveColumns
     */
    public function setColumns(UxonObject $columns);
    
    /**
     * Returns TRUE if the widget has at least one column at the moment and FALSE otherwise.
     *
     * @return boolean
     */
    public function hasColumns();
    
    /**
     * 
     * @param DataColumn $column
     * @return iHaveColumns
     */
    public function removeColumn(DataColumn $column);
    
    /**
     * @return string
     */
    public function getColumnDefaultWidgetType() : string;
}