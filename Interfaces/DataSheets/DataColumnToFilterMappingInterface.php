<?php
namespace exface\Core\Interfaces\DataSheets;

/**
 * Maps one data sheet column to a filter condition of another sheet.
 *
 * Columns are identified by expressions (e.g. attribute alias, formula, etc.).
 *
 * @author Andrej Kabachnik
 *
 */
interface DataColumnToFilterMappingInterface extends DataColumnMappingInterface
{
    public function getComparator();
    
    public function setComparator($string);
}