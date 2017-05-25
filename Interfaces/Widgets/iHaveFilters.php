<?php
namespace exface\Core\Interfaces\Widgets;

interface iHaveFilters extends iHaveChildren
{

    public function addFilter(\exface\Core\Widgets\AbstractWidget $filter_widget);

    public function getFilters();

    public function getFiltersApplied();

    public function setFilters(array $filters);
}