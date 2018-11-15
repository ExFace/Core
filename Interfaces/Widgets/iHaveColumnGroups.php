<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\DataColumnGroup;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveColumnGroups extends WidgetInterface
{

    public function addColumnGroup(DataColumnGroup $column);

    public function getColumnGroups();
}