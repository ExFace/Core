<?php
namespace exface\Core\Interfaces\DataSheets;

/**
 * Maps all filters matching the given expression from one sheet to a column of another sheet.
 *
 * @author Andrej Kabachnik
 *
 */
interface DataFilterToColumnMappingInterface extends DataColumnMappingInterface
{
    /**
     * 
     * @param string $string
     * @return DataFilterToColumnMappingInterface
     */
    public function setFromComparator(string $string) : DataFilterToColumnMappingInterface;
    
    /**
     *
     * @param bool $value
     * @return DataFilterToColumnMappingInterface
     */
    public function setToSingleRow(bool $value) : DataFilterToColumnMappingInterface;
    
    /**
     * A separator to concatenate all values into a single row in the resulting column.
     *
     * @param string $value
     * @return DataFilterToColumnMappingInterface
     */
    public function setToSingleRowSeparator(string $value) : DataFilterToColumnMappingInterface;
}