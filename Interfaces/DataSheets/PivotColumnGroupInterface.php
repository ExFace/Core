<?php
namespace exface\Core\Interfaces\DataSheets;

/**
 * Represents a pivot column group: one column for the transposed headers and multiple value-columns
 * 
 * @author Andrej Kabachnik
 *
 */
interface PivotColumnGroupInterface
{
    /**
     * 
     * @return DataColumnInterface
     */
    public function getColumnWithHeaders() : DataColumnInterface;
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getDataSheet() : DataSheetInterface;
    
    /**
     * 
     * @param DataColumnInterface $column
     * @return PivotColumnGroupInterface
     */
    public function addColumnWithValues(DataColumnInterface $column) : PivotColumnGroupInterface;
    
    /**
     * 
     * @return DataColumnInterface[]
     */
    public function getColumnsWithValues() : array;
    
    /**
     * 
     * @param DataColumnInterface $col
     * @return bool
     */
    public function hasColumnWithValues(DataColumnInterface $col) : bool;
    
    /**
     * 
     * @return int
     */
    public function countColumnsWithValues() : int;
    
    /**
     *
     * @param string $colName
     * @return DataColumnInterface|NULL
     */
    public function getColumnByName(string $colName) : ?DataColumnInterface;
    
    /**
     *
     * @param DataColumnInterface $col
     * @return int
     */
    public function getColumnIndex(DataColumnInterface $col) : int;
}