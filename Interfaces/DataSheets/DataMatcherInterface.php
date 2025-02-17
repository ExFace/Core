<?php
namespace exface\Core\Interfaces\DataSheets;

/**
 * Common interface for match between two data sheets
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataMatcherInterface
{
    public function getMainDataSheet() : DataSheetInterface;
    
    public function getName() : ?string;
    
    public function hasMatches() : bool;
    
    /**
     * 
     * @return DataMatchInterface[]
     */
    public function getMatches() : array;
    
    /**
     * Get all matches for the given row index (starting with 0)
     * 
     * @param int $mainSheetRowIdx
     * @return DataMatchInterface[]
     */
    public function getMatchesForRow(int $mainSheetRowIdx) : array;
    
    /**
     * 
     * @return int[]
     */
    public function getMatchedRowIndexes() : array;
}