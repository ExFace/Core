<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\DataColumnGroup;

interface iHaveColumnGroups extends iHaveChildren
{

    public function addColumnGroup(DataColumnGroup $column);

    public function getColumnGroups();
}