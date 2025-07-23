<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\Utils\DataTracker;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * This `DataTracker` is specialized for tracking data-sheet transformations.
 * 
 * @see DataTracker
 */
class DataSheetTracker extends DataTracker
{
    protected array $trackedAliases = [];

    /**
     * Initializes the tracker with base data from an array of columns.
     *
     * NOTE: You cannot alter the base data later, so make sure you pass a complete data set!
     *
     * @param DataColumnInterface[] $columns
     * @see DataTracker::__construct()
     */
    public function __construct(array $columns, bool $deduplicate = false)
    {
        parent::__construct($this->columnsToRows($columns), $deduplicate);
        $this->markColumnsAsTracked($columns);
    }

    /**
     * Creates a set of rows from an array of columns.
     * 
     * @param DataColumnInterface[] $columns
     * @return array
     */
    protected function columnsToRows(array $columns) : array
    {
        $result = [];

        foreach ($columns as $column) {
            $alias = $column->getAttributeAlias();
            foreach ($column->getValues() as $rowNr => $value) {
                $result[$rowNr][$alias] = $value;
            }
        }

        return $result;
    }

    /**
     * Mark a set of columns for tracking.
     * 
     * @param DataColumnInterface[]|string[] $columns
     * A list of columns and/or attribute aliases that you wish to mark.
     * @return void
     */
    protected function markColumnsAsTracked(array $columns) : void
    {
        foreach ($columns as $column) {
            $alias = is_string($column) ? $column : $column->getAttributeAlias();
            $this->trackedAliases[$alias] = $alias;
        }
    }

    /**
     * @param DataColumnInterface|string $column
     * @return bool
     */
    public function isTrackedColumn(DataColumnInterface|string $column) : bool
    {
        $alias = is_string($column) ? $column : $column->getAttributeAlias();
        return key_exists($alias, $this->trackedAliases);
    }

    /**
     * Returns all currently tracked attribute aliases.
     * 
     * @return array
     */
    public function getTrackedAliases() : array
    {
        return $this->trackedAliases;
    }

    /**
     * @inheritDoc
     * @see DataTracker::recordDataTransform()
     */
    public function recordDataTransform(
        array $fromColumns,
        array $toColumns,
        int $preferredVersion = -1
    ) : int
    {
        $result = parent::recordDataTransform(
            $this->columnsToRows($fromColumns),
            $this->columnsToRows($toColumns),
            $preferredVersion
        );

        if($result > -1) {
            $this->markColumnsAsTracked($toColumns);
        }

        return $result;
    }

    /**
     * @inheritDoc
     * @see DataTracker::getLatestVersionForData()
     */
    public function getLatestVersionForData(array $columns) : int
    {
        return parent::getLatestVersionForData($this->columnsToRows($columns));
    }

    /**
     * Tracks your data set back to its base data.
     * 
     * @param DataSheetInterface $dataSheet
     * @param array              $failedToFind
     * @param callable|null      $toRowNumber
     * @return array
     * @see DataTracker::getBaseData()
     */
    public function getBaseDataForSheet(
        DataSheetInterface $dataSheet, 
        array &$failedToFind, 
        callable $toRowNumber = null
    ) : array
    {
        $toRowNumber = $toRowNumber ?? function ($index) { return $index; };
        
        $failedToFindIndices = [];
        $columns = $dataSheet->getColumns()->getMultiple($this->trackedAliases);
        $baseData = $this->getBaseData($this->columnsToRows($columns), $failedToFindIndices);

        foreach ($failedToFindIndices as $index) {
            $rowNr = call_user_func($toRowNumber, $index);
            $failedToFind[$rowNr] = $dataSheet->getRow($index);
        }

        if($baseData === false) {
            return [];
        }

        return array_combine(
            array_map(
                $toRowNumber,
                array_keys($baseData)
            ),
            $baseData
        );
    }
}