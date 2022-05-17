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
     * @return DataColumnInterface|NULL
     */
    public function getColumn() : ?DataColumnInterface;
    
    /**
     * 
     * @return int|NULL
     */
    public function getRowNumber() : ?int;
    
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
     * Returns the values pointed to: cell value, array of values of a column or an entire row as an associative array
     * 
     * The optional arguments allow some handy normalization:
     * 
     * - `$splitLists` and `$splitDelimiter` will look for values, that are delimited 
     * lists and split them into arrays
     * - `$onlyUnique` will remove duplicates from any array values. Additionally, if
     * an array will end up with only a single value, that value will be returned as
     * scalar (not as array)
     * 
     * @param bool $splitLists
     * @param string $splitDelimiter
     * @param bool $onlyUnique
     * @return mixed|NULL
     */
    public function getValue(bool $splitLists = false, string $splitDelimiter = null, bool $onlyUnique = false);
    
    /**
     * 
     * @return bool
     */
    public function hasValue() : bool;
    
    /**
     * 
     * @return bool
     */
    public function hasMultipleValues() : bool;
}