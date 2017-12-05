<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;

interface iHaveColumns extends iHaveChildren
{

    public function addColumn(\exface\Core\Widgets\DataColumn $column);

    public function getColumns();

    public function setColumns(UxonObject $columns);
    
    /**
     * Returns TRUE if the widget has at least one column at the moment and FALSE otherwise.
     *
     * @return boolean
     */
    public function hasColumns();
}