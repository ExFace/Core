<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\QueryBuilder\RowDataArrayFilter;
use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;

/**
 * Data type for arrays of data rows - like those inside the data sheet
 * 
 * @author Andrej Kabachnik
 *
 */
class RowArrayDataType extends ArrayDataType
{
    /**
     * 
     * @param array $rows
     * @return array
     */
    public static function findUniqueRows(array $rows) : array
    {
        return array_map("unserialize", array_unique(array_map("serialize", $rows)));
    }
    
    /**
     * Allows to filter data by building up a filter object and applying it at the end
     * 
     * Example:
     * 
     * ```
     *  $filteredRows = RowArrayDataType::filter()
     *      ->addAnd('COL1', 'val1', ComparatorDataType::EQUALS)
     *      ->addAnd('COL2', 'val2', ComparatorDataType::GREATER)
     *      ->filter($rows);
     * ```
     * 
     * @return RowDataArrayFilter
     */
    public static function filter() : RowDataArrayFilter
    {
        return new RowDataArrayFilter();
    }
    
    /**
     * Allows to sort data by building up a sorter object and applying it at the end.
     * 
     * Example:
     * 
     * ```
     *  $sortedRows = RowArrayDataType::sorter()
     *      ->addCriteria('COL1', SortingDirectionsDataType::ASC)
     *      ->addCriteria('COL2', SortingDirectionsDataType::DESC)
     *      ->sort($rows);
     * ```
     * 
     * @return RowDataArraySorter
     */
    public static function sorter() : RowDataArraySorter
    {
        return new RowDataArraySorter();
    }
}