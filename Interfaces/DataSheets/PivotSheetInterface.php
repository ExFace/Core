<?php
namespace exface\Core\Interfaces\DataSheets;

/**
 * A special data sheet, that can transpose data in certain columns.
 * 
 * @author Andrej Kabachnik
 *
 */
interface PivotSheetInterface extends DataSheetInterface
{
    public function addColumnToTranspose(DataColumnInterface $valuesColumn, DataColumnInterface $headersColumn) : PivotSheetInterface;
    
    /**
     *
     * @return PivotColumnGroupInterface[]
     */
    public function getPivotColumnGroups() : array;
    
    public function getPivotResultDataSheet() : DataSheetInterface;
    
    /**
     *
     * @return array
     */
    public function getRowsUnpivoted() : array;
    
    /**
     *
     * @return array
     */
    public function getRowsPivoted() : array;
    
    /**
     * 
     * @param DataColumnInterface $col
     * @return bool
     */
    public function isColumnWithPivotValues(DataColumnInterface $col) : bool;
    
    /**
     *
     * @param DataColumnInterface $col
     * @return bool
     */
    public function isColumnWithPivotHeaders(DataColumnInterface $col) : bool;
    
    /**
     * 
     * @param DataColumnInterface $col
     * @return PivotColumnGroupInterface|NULL
     */
    public function getPivotColumnGroup(DataColumnInterface $col) : ?PivotColumnGroupInterface;
}