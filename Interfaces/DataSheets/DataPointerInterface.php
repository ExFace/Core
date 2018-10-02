<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * The data pointer is an immutable reference to data in a data sheet.
 * 
 * It can reference a cell, all cells of a column or an entire row.
 * 
 * @author aka
 *
 */
interface DataPointerInterface extends WorkbenchDependantInterface
{

    /**
     * 
     * @return DataSheetInterface
     */
    public function getDataSheet() : DataSheetInterface;
    
    /**
     * 
     * @return DataColumnInterface
     */
    public function getColumn() : DataColumnInterface;
    
    /**
     * 
     * @return int
     */
    public function getRowNumber() : int;
    
    /**
     * 
     * @return bool
     */
    public function isEmpty() : bool;
    
    /**
     * 
     * @return bool
     */
    public function isCell() : bool;
    
    /**
     * 
     * @return bool
     */
    public function isRow() : bool;
    
    /**
     * 
     * @return bool
     */
    public function isColumn() : bool;
    
    /**
     * @return mixed
     */
    public function getValue();
}