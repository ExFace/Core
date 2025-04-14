<?php
namespace exface\Core\CommonLogic\DataSheets\Matcher;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataMatcherInterface;
use exface\Core\Interfaces\DataSheets\DataMatchInterface;

/**
 * Default implementation of a row matcher to compare rows between two data sheets
 * 
 * A row of the compare-sheet is concidered a match if all values of the specified
 * compare-columns match the values of the respective columns of the main sheet.
 * 
 * Both sheets MUST have all of the compare columns!
 * 
 * Additionally there are some parameters, that can be set after instantiation:
 * 
 * - `setIgnoreUidMatches(true)` will not concider a compared row a match if the
 * UID values are the same
 * - `setCompareCaseSensitive(true)` will perform a case sensitive comparison
 * - `setMaxMatchesPerRow()` allows to 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataRowMatcher implements DataMatcherInterface
{
    private $mainSheet = null;
    
    private $compareSheet = null;
    
    private $compareColumns = null;
    
    private $name = null;
    
    private $maxMatcherPerRow = null;
    
    private $matches = null;
    
    private $ignoreUidMatches = false;
    
    private $compareCaseSensitive = false;
    
    private $stopAfterFirst = false;
    
    public function __construct(DataSheetInterface $mainSheet, DataSheetInterface $compareSheet, array $compareColumns, string $name = null)
    {
        $this->mainSheet = $mainSheet;
        $this->compareSheet = $compareSheet;
        $this->compareColumns = $compareColumns;
        $this->name = $name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::getMainDataSheet()
     */
    public function getMainDataSheet() : DataSheetInterface
    {
        return $this->mainSheet;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getCompareDataSheet() : DataSheetInterface
    {
        return $this->compareSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::getName()
     */
    public function getName() : ?string
    {
        return $this->name;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getIgnoreUidMatches() : bool
    {
        return $this->ignoreUidMatches;
    }
    
    /**
     * 
     * @param bool $value
     * @return DataRowMatcher
     */
    public function setIgnoreUidMatches(bool $value) : DataRowMatcher
    {
        $this->ignoreUidMatches = $value;
        return $this;
    }
    
    /**
     * 
     * @return int|NULL
     */
    protected function getMaxMatchesPerRow() : ?int
    {
        return $this->maxMatcherPerRow;
    }
    
    /**
     * 
     * @param int $value
     * @return DataRowMatcher
     */
    public function setMaxMatchesPerRow(int $value) : DataRowMatcher
    {
        $this->maxMatcherPerRow = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getStopAfterFirst() : bool
    {
        return $this->stopAfterFirst;
    }
    
    /**
     * 
     * @param bool $value
     * @return DataRowMatcher
     */
    public function setStopAfterFirst(bool $value) : DataRowMatcher
    {
        $this->stopAfterFirst = $value;
        return $this;
    }
    
    /**
     * 
     * @return DataRowMatch[]
     */
    public function getMatches() : array
    {
        $flat = [];
        foreach ($this->getMatchesPerRow() as $match) {
            $flat[] = $match;
        }
        return $flat;
    }
    
    /**
     * 
     * @return DataMatchInterface[][]
     */
    protected function getMatchesPerRow() : array
    {
        if ($this->matches === null) {
            $this->matches = $this->findMatches(
                $this->mainSheet->getRows(),
                $this->compareSheet->getRows(),
                $this->compareColumns,
            );
        }
        return $this->matches;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::getMatchesForRow()
     */
    public function getMatchesForRow(int $mainSheetRowIdx) : array
    {
        return $this->getMatchesPerRow()[$mainSheetRowIdx] ?? [];
    }
    
    /**
     *
     * @return bool
     */
    protected function getCompareCaseSensitive() : bool
    {
        return $this->compareCaseSensitive;
    }
    
    /**
     * Set to TRUE for case sensitive string comparison
     *
     * @param bool $value
     * @return DataRowMatcher
     */
    public function setCompareCaseSensitive(bool $value) : DataRowMatcher
    {
        $this->compareCaseSensitive = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::getMatchedRowIndexes()
     */
    public function getMatchedRowIndexes() : array
    {
        return array_keys($this->getMatchesPerRow());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMatcherInterface::hasMatches()
     */
    public function hasMatches() : bool
    {
        return empty($this->getMatchesPerRow()) === false;
    }
    
    /**
     * 
     * @param array $mainRows
     * @param array $checkRows
     * @param array $compareCols
     * @return array
     */
    protected function findMatches(array $mainRows, array $checkRows, array $compareCols) : array
    {
        $matches = [];
        $uidCol = $this->getMainDataSheet()->hasUidColumn() ? $this->getMainDataSheet()->getUidColumn() : null;
        $mainRowCnt = count($mainRows);
        $checkRowCnt = count($checkRows);
        $caseSensitive = $this->getCompareCaseSensitive();
        $selfCompare = ($mainRows === $checkRows);
        $ignoreUidMatches = $this->getIgnoreUidMatches();
        $maxMatchesPerRow = $this->getMaxMatchesPerRow();
        $stopAfterFirst = $this->getStopAfterFirst();
        
        // Extract and parse values relevant for the search. Do it once here in order to
        // improve performance on large data sets.
        $mainRowsKeys = [];
        $checkRowsKeys = [];
        $keyCols = ($uidCol !== null ? array_merge($compareCols, [$uidCol]) : $compareCols);
        foreach ($keyCols as $col) {
            $key = $col->getName();
            $type = $col->getDataType();
            foreach ($mainRows as $mainRowIdx => $mainRow) {
                $mainRowsKeys[$mainRowIdx][$key] = $type->parse($mainRow[$key]);
            }
            foreach ($checkRows as $checkRowIdx => $checkRow) {
                $checkRowsKeys[$checkRowIdx][$key] = $type->parse($checkRow[$key]);
            }
        }
        
        // Now compare the keys of each event row to each check row
        for ($mainRowIdx = 0; $mainRowIdx < $mainRowCnt; $mainRowIdx++) {
            // For each row being saved iterate over all the rows from the data source
            // NOTE: in self-compare mode (when looking for duplicates inside the data sheet) only
            // iterate over the following rows, because previous ones were already checked.
            // This also makes sure, the first row is not marked as duplicate of one of the subsequent rows
            $uidMatchProcessed = false;
            $mainRow = $mainRowsKeys[$mainRowIdx];
            for ($checkRowIdx = ($selfCompare === true ? $mainRowIdx+1 : 0); $checkRowIdx < $checkRowCnt; $checkRowIdx++) {
                $checkRow = $checkRowsKeys[$checkRowIdx];
                $isDuplicate = true;
                $isUidMatch = false;
                // Compare all the relevant columns: if any value differs, it is NOT a duplicate
                foreach ($compareCols as $col) {
                    $key = $col->getName();
                    $mainVal = $mainRow[$key];
                    $checkVal = $checkRow[$key];
                    // If both values are strings, use a case-insensitive comparison if required
                    // Otherwise compare directly, but WITHOUT strict type check. This ensures,
                    // that a string "1" is equal to an integer 1, etc.
                    if (is_string($mainVal) && is_string($checkVal) && $caseSensitive === false) {
                        if (strcasecmp($mainVal, $checkVal) !== 0) {
                            $isDuplicate = false;
                            break;
                        }
                    } elseif ($mainVal != $checkVal) {
                        $isDuplicate = false;
                        break;
                    }
                }
                
                // If the data source row has matching columns, check if the UID also matches: if so,
                // it is the same row and, thus, NOT a duplicate. If there is no UID, just ignore the
                // first match.
                if ($isDuplicate === true && $uidMatchProcessed === false) {
                    if ($uidCol !== null) {
                        $key = $uidCol->getName();
                        $mainVal = $mainRow[$key];
                        $checkVal = $checkRow[$key];
                        // If both values are strings, use a case-insensitive comparison if required
                        // Otherwise compare directly, but WITHOUT strict type check. This ensures,
                        // that a string "1" is equal to an integer 1, etc.
                        if (is_string($mainVal) && is_string($checkVal) && $caseSensitive === false) {
                            if (strcasecmp($mainVal, $checkVal) === 0) {
                                $isUidMatch = true;
                            }
                        } elseif ($mainVal == $checkVal) {
                            $isUidMatch = true;
                        }
                    } else {
                        $isUidMatch = true;
                    }
                }
                
                if ($isUidMatch === true) {
                    $uidMatchProcessed = true;
                    if ($ignoreUidMatches === true && $uidCol !== null) {
                        $isDuplicate = false;
                        // Don't break here as other $checkRows may still be duplicates!!!
                    }
                }
                
                // If it is still a potential duplicate, it really is one
                if ($isDuplicate === true) {
                    $matches[$mainRowIdx][] = new DataRowMatch($this, $mainRowIdx, $checkRowIdx, $isUidMatch);
                    if ($selfCompare === true) {
                        $matches[$checkRowIdx][] = new DataRowMatch($this, $checkRowIdx, $mainRowIdx, $isUidMatch);
                    }
                    if ($stopAfterFirst) {
                        return $matches;
                    }
                    if ($maxMatchesPerRow === 1 || $maxMatchesPerRow >= count($matches[$mainRowIdx])) {
                        break;
                    }
                }
            }
        }
        
        return $matches;
    }
}