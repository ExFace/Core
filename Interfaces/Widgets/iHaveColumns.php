<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;

interface iHaveColumns extends iHaveChildren
{

    public function addColumn(\exface\Core\Widgets\DataColumn $column);

    public function getColumns();

    public function setColumns(UxonObject $columns);
}