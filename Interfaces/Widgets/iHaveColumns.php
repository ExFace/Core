<?php
namespace exface\Core\Interfaces\Widgets;

interface iHaveColumns extends iHaveChildren
{

    public function addColumn(\exface\Core\Widgets\DataColumn $column);

    public function getColumns();

    public function setColumns(array $columns);
}