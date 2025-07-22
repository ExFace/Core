<?php

namespace exface\Core\CommonLogic\DataSheets;

use axenox\ETL\Common\StepNote;
use axenox\ETL\Interfaces\ETLStepDataInterface;
use exface\Core\CommonLogic\Debugger\LogBooks\FlowStepLogBook;
use exface\Core\CommonLogic\Utils\DataTracker;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Exceptions\DataTrackerException;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * This a wrapper for `DataTracker` specialized in dealing with data from `DataSheet` instances.
 * It does not derive from a common interface or from `DataTracker` to allow for better clarity
 * with function footprints.
 * 
 * @see DataTracker
 */
class DataSheetTracker
{
    protected DataTracker $dataTracker;
    protected array $trackedAliases = [];

    /**
     * Initializes the tracker with base data from an array of columns. 
     * 
     * NOTE: You cannot alter the base data later, so make sure you pass a complete data set!
     * 
     * @param DataColumnInterface[] $columns
     * @param ETLStepDataInterface  $stepData
     * @param FlowStepLogBook       $logBook
     */
    public function __construct(
        array $columns,
        ETLStepDataInterface $stepData,
        FlowStepLogBook $logBook
    )
    {
        try {
            $this->dataTracker = new DataTracker($this->columnsToRows($columns));
        } catch (DataTrackerException $exception) {
            StepNote::fromException(
                $columns[0]->getWorkbench(),
                $stepData,
                $exception,
                null,
                false
            )->addRowsAsContext(
                $exception->getBadData()
            )->setMessageType(
                MessageTypeDataType::WARNING
            )->takeNote();

            $logBook->addLine('**WARNING** - Data tracking not possible: ' . $exception->getMessage());
        }

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
     * Record a transform.
     * 
     * @param DataColumnInterface[] $fromColumns
     * @param DataColumnInterface[] $toColumns
     * @param int   $preferredVersion
     * @return int
     * @see DataTracker::recordDataTransform()
     */
    public function recordDataTransform(
        array $fromColumns,
        array $toColumns,
        int $preferredVersion = -1
    ) : int
    {
        $result = $this->dataTracker->recordDataTransform(
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
     * @param array $columns
     * @return int
     * @see DataTracker::getLatestVersionForData()
     */
    public function getVersionForData(array $columns) : int
    {
        return $this->dataTracker->getLatestVersionForData($this->columnsToRows($columns));
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
    public function getBaseData(
        DataSheetInterface $dataSheet, 
        array &$failedToFind, 
        callable $toRowNumber = null
    ) : array
    {
        $toRowNumber = $toRowNumber ?? function ($index) { return $index; };
        
        $failedToFindIndices = [];
        $columns = $dataSheet->getColumns()->getMultiple($this->trackedAliases);
        $baseData = $this->dataTracker->getBaseData($this->columnsToRows($columns), $failedToFindIndices);

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