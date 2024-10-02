<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;

interface iHaveFilters extends WidgetInterface
{

    public function addFilter(WidgetInterface $filter_widget) : iHaveFilters;

    public function getFilters() : array;
    
    /**
     * Returns all filters, that have values and thus will be applied to the result
     *
     * @return iFilterData[]
     */
    public function getFiltersApplied() : array;

    /**
     * @param UxonObject[] $uxon_objects
     * @return iHaveFilters
     */
    public function setFilters(UxonObject $uxon_objects) : iHaveFilters;

    /**
     * Creates a `Filter` widget from the given attribute alias an/or a UXON description of a `Filter` or `Input` widget.
     * 
     * NOTE: the filter is NOT added to the filters-tab automatically!!!
     * 
     * @param UxonObject $uxon_object
     * @return iFilterData
     */
    public function createFilter(UxonObject $uxon = null) : iFilterData;

    /**
     * 
     * @return bool
     */
    public function hasFilters() : bool;
}