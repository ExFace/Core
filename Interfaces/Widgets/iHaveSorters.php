<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Widgets with this interface have a list of sorters for their data.
 * 
 * @see WidgetConfigurator for a details explanation.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveSorters extends WidgetInterface
{
    /**
     * Returns an all data sorters applied to this sheet as an array.
     *
     * @return UxonObject[]
     */
    public function getSorters() : array;
    
    /**
     * Defines sorters for the data via array of sorter objects.
     *
     * TODO use widget parts for sorters instead of plain uxon objects
     *
     * @param UxonObject $sorters
     */
    public function setSorters(UxonObject $sorters) : iHaveSorters;
    
    /**
     * 
     * @param string $attribute_alias
     * @param string $direction
     * @return iHaveSorters
     */
    public function addSorter(string $attribute_alias, string $direction) : iHaveSorters;
}