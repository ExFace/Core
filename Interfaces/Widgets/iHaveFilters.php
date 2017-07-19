<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\Filter;

interface iHaveFilters extends iHaveChildren
{

    public function addFilter(\exface\Core\Widgets\AbstractWidget $filter_widget);

    public function getFilters();
    
    public function getFilter($filter_widget_id);
    
    /**
     * Returns all filters, that have values and thus will be applied to the result
     *
     * @return Filter[]
     */
    public function getFiltersApplied();

    public function setFilters(array $filters);
}