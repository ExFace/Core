<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataSorterList;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Utils\LazyHierarchicalDataCache;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\DataSheets\DataSheetWriteError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Orders data rows automatically and saves the order number in one of the attributes.
 * 
 * This behavior orders all data within configured boundaries: e.g.
 * 
 * - all pages (`exface.Core.PAGE`) within a `MENU_PARENT` 
 * - all order positions within an `ORDER`
 * 
 * Every time a row of the ordered object is saved, this behavior makes sure the order
 * number storen in `order_number_attribute` is updated - for the saved row, but also
 * for other rows within the same ordering boundies if needed.
 * 
 * ## Configuration
 * 
 * - `order_number_attribute` (required) - save the order number here
 * - `order_with_attributes` - all rows with the same value of these attributes will share an ordering sequence.
 * - `order_starts_with` - the order number for the first row
 * - `close_gaps` - by default, deleting a row in the middle of the sequence will update order numbers of subsequent 
 * rows to close the gap. You can explicitly allow gaps by setting this option to `false`. 
 * - `append_to_end` - set to `false` to put a new row without an explicitly defined order number at the beginning
 * (that is with `order_starts_with` as number) instead of at the end
 * 
 * ## Examples
 * 
 * ### Order pages by their position within their menu parent. Closing gaps and inserting new elements on top.
 * 
 * Example config to order the menu positions of `exface.Core.PAGE`.
 * 
 * ```
 * 
 *  {
 *      "order_number_attribute": "MENU_POSITION",
 *      "order_within_attributes": [
 *          "MENU_PARENT"
 *      ],
 *      "order_starts_with": 0,
 *      "append_to_end": true,
 *      "close_gaps": true
 *  }
 * 
 * ```
 * 
 * @author Miriam Seitz, Georg Bieger
 *
 */
class OrderingBehavior extends AbstractBehavior
{
    // internal variables
    private bool $working = false;

    // configurable variables
    private int $startIndex = 1;
    private bool $closeGaps = true;
    private bool $insertWithMaxIndex = true;
    private mixed $orderAttributeAlias = null;
    private mixed $orderBoundaryAliases = [];
    
    private array $pendingChanges = [];

    /**
     *
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners(): BehaviorInterface
    {
        $priority = $this->getPriority();
        
        $determineOrderHandle = array($this, 'onHandleDetermineOrder');
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), $determineOrderHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), $determineOrderHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), $determineOrderHandle, $priority);

        $applyChangesHandle = array($this, 'onHandleApplyChanges');
        $this->getWorkbench()->eventManager()->addListener(OnCreateDataEvent::getEventName(), $applyChangesHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnUpdateDataEvent::getEventName(), $applyChangesHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnDeleteDataEvent::getEventName(), $applyChangesHandle, $priority);

        return $this;
    }

    /**
     *
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners(): BehaviorInterface
    {
        $determineOrderHandle = array($this, 'onHandleDetermineOrder');
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeCreateDataEvent::getEventName(), $determineOrderHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), $determineOrderHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), $determineOrderHandle);

        $applyChangesHandle = array($this, 'onHandleApplyChanges');
        $this->getWorkbench()->eventManager()->removeListener(OnCreateDataEvent::getEventName(), $applyChangesHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnUpdateDataEvent::getEventName(), $applyChangesHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnDeleteDataEvent::getEventName(), $applyChangesHandle);

        return $this;
    }

    /**
     * Determine the order of data elements during `OnBefore...Data` and cache the calculated changes to apply them 
     * during `On...Data` events. 
     * 
     * @param DataSheetEventInterface $event
     */
    public function onHandleDetermineOrder(DataSheetEventInterface $event): void
    {
        // Ignore MetaObjects we are not associated with.
        if (!$event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        // Prevent recursion.
        if ($this->working === true) {
            return;
        }

        // Create logbook.
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        // Get datasheet.
        $eventSheet = $event->getDataSheet();
        $logbook->addDataSheet('InputData', $eventSheet);
        // Make sure it has a UID column.
        if ($eventSheet->hasUidColumn() === false) {
            $logbook->addLine('Cannot order objects with no Uid attribute.');
            return;
        }
        
        // Begin work.
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        $this->working = true;

        // Fetch any missing columns.
        $this->fetchMissingColumns($eventSheet, $logbook);
        $logbook->addLine('Received ' . $eventSheet->countRows() . ' rows of ' . $eventSheet->getMetaObject()->__toString());
        $logbook->addDataSheet('Data', $eventSheet);

        // Order data.
        $pendingChanges = [];
        $onDelete = $event instanceof OnBeforeDeleteDataEvent || $event instanceof OnDeleteDataEvent;
        $this->groupDataByParent($onDelete, $eventSheet, $pendingChanges, $logbook);

        // Apply changes, if any.
        if (count($pendingChanges) === 0) {
            $logbook->addLine('No changes to order necessary.');
        } else {
            $logbook->addLine('Queued pending changes to be applied in the follow-up event.');
            $this->pendingChanges[] = [
                'eventSheet' => $eventSheet,
                'changes' => $pendingChanges
            ];
        }

        // Finish work.
        $this->working = false;
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    /**
     * Apply changes calculated during `OnBefore...Data`.
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function onHandleApplyChanges (DataSheetEventInterface $event) : void
    {
        // Ignore MetaObjects we are not associated with.
        if (!$event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

        if($this->working) {
            return;
        }
        
        // Create logbook.
        $this->working = true;
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        
        $eventSheet = $event->getDataSheet();
        $logbook->addLine('Found input data.');
        $logbook->addDataSheet('InputData', $eventSheet);
        
        $pendingChanges = [];
        foreach ($this->pendingChanges as $pending) {
            if($pending['eventSheet'] === $eventSheet) {
                $pendingChanges = $pending['changes'];
                break;
            }
        }
        
        if(empty($pendingChanges)) {
            $logbook->addLine('No changes pending for input data.');
        } else {
            $logbook->addLine('Found ' . count($pendingChanges) . ' pending changes for input data.');
            $this->applyChanges($eventSheet, $pendingChanges, $logbook);
        }
        
        $this->working = false;
    }

    /**
     * Loads any missing parent columns and merges their data into the event sheet.
     *
     * @param DataSheetInterface $eventSheet
     * @param BehaviorLogBook    $logBook
     * @return void
     */
    function fetchMissingColumns(
        DataSheetInterface $eventSheet,
        BehaviorLogBook $logBook): void
    {
        $logBook->addLine('Ensuring required data...');
        $logBook->addIndent(1);
        
        // Load missing parent columns.
        $fetchSheet = $this->createEmptyCopy($eventSheet, true, true);
        $sampleRow = $eventSheet->getRow();
        foreach ($this->getParentAliases() as $parentAlias) {
            // If the row is not present in the event sheet, we need to fetch it.
            if (!key_exists($parentAlias, $sampleRow)) {
                $logBook->addLine($parentAlias . ' is missing in event data and will have to be loaded.');
                $fetchSheet->getColumns()->addFromExpression($parentAlias);
            } else {
                $logBook->addLine($parentAlias . ' is already present in event data.');
            }
        }
        // Load missing column data and merge it into the event sheet.
        if ($fetchSheet->getColumns()->count() > 0) {
            $fetchSheet->getFilters()->addConditionFromColumnValues($eventSheet->getUidColumn(), true);
            $fetchSheet->dataRead();
            
            $logBook->addLine('Successfully loaded missing data.');
            $logBook->addDataSheet('Missing Data (loaded)', $fetchSheet);
            
            $eventSheet->joinLeft($fetchSheet, $eventSheet->getUidColumnName(), $fetchSheet->getUidColumnName());
        } else {
            $logBook->addLine('All required data is already present, no further loading necessary.');
        }
        
        $logBook->addIndent(-1);
    }

    /**
     * Groups all rows in the event sheet by their parents and
     * applies an ascending order within the groups according to their index alias.
     *
     * @param bool               $onDelete
     * @param DataSheetInterface $eventSheet
     * @param array              $pendingChanges
     * An array to which any changes necessary to achieve the desired ordering will be appended.
     * @param BehaviorLogBook    $logbook
     * @return void
     */
    private function groupDataByParent(
        bool                $onDelete,
        DataSheetInterface  $eventSheet,
        array               &$pendingChanges,
        BehaviorLogBook     $logbook): void
    {
        $logbook->addLine('Ordering event data...');
        $logbook->addIndent(1);
        
        // Prepare variables.
        $cache = new LazyHierarchicalDataCache();
        $indexAlias = $this->getOrderNumberAttributeAlias();
        $uidAlias = $eventSheet->getUidColumnName();

        // Iterate over rows in the event sheet and update their ordering.
        foreach ($eventSheet->getRows() as $rowIndex => $row) {
            // Find, load and cache the row and its siblings.
            $logbook->addLine('Searching for siblings of row '. $rowIndex . ':');
            $siblingData = $this->getSiblings($onDelete, $eventSheet, $pendingChanges, $row, $indexAlias, $cache, $logbook);

            // If we already ordered the group this row belongs to, continue.
            if ($siblingData === "PROCESSED") {
                $logbook->addLine('Data for this group has already been processed, moving on.');
                continue;
            }

            // Find and record changes.
            $this->findPendingChanges($siblingData, $pendingChanges, $logbook);

            // Mark all siblings as DONE, to avoid ordering the same group multiple times.
            $cache->setData($siblingData->getRow(0)[$uidAlias], "PROCESSED");
        }

        unset($cache);
        $logbook->addIndent(-1);
    }

    /**
     * Finds all siblings of a given row. A sibling is any row that has the EXACT same parentage, including EMPTY
     * values.
     *
     * Loads, processes and caches all relevant data for this row and its siblings.
     *
     * Multiple calls to this function with either the same row or one of its siblings will return the cached data
     * without performing any additional work.
     *
     * @param bool                      $onDelete
     * @param DataSheetInterface        $eventSheet
     * @param array                     $pendingChanges
     * An array to which any changes necessary to achieve the desired ordering will be appended.
     * @param array                     $row
     * @param string                    $indexingAlias
     * @param LazyHierarchicalDataCache $cache
     * @param BehaviorLogBook           $logbook
     * @return DataSheetInterface|string
     */
    private function getSiblings(
        bool                      $onDelete,
        DataSheetInterface        $eventSheet,
        array                     &$pendingChanges,
        array                     $row,
        string                    $indexingAlias,
        LazyHierarchicalDataCache $cache,
        BehaviorLogBook          $logbook): array|string
    {
        // Check if we already loaded the siblings for this UID before.
        $uidAlias = $eventSheet->getUidColumnName();
        if ($siblingData = $cache->getData($row[$uidAlias])) {
            $logbook->addLine('Sibling data retrieved from cache.');
            return $siblingData;
        }

        // Prepare variables.
        $loadedData = $this->createEmptyCopy($eventSheet, true, false);
        $parents = $this->addFiltersFromParentAliases($loadedData, $row);
        $groupId = json_encode($parents);
        $logbook->addIndent(1);
        $logbook->addLine('Looking other elements in group ' . $groupId . '.');
        // Load old data from database.
        $loadedData->dataRead();
        // And remove any rows that do not belong to this group.
        foreach ($loadedData->getRows() as $siblingRow) {
            if (!$this->belongsToGroup($siblingRow, $parents)) {
                $loadedData->removeRowsByUid($siblingRow[$uidAlias]);
            }
        }
        // Get all updated rows from the event that belong to this group.
        $changedData = $eventSheet->extract($loadedData->getFilters());
        
        if($onDelete) {
            $rows = $loadedData->getRows();
            $rowCount = count($rows);
            if($rowCount === 0) {
                $logbook->addLine('All siblings have been deleted. No data needs to be ordered in this group.');
                return "PROCESSED";
            } else {
                $logbook->addLine($rowCount . ' left to order, after excluding deleted data. Using row ' . $row[$uidAlias] . ' as reference.');
                $row = $rows[0];
            }
        } else {
            $logbook->addLine('Fetching new data from changed rows.');
            $loadedData->addRows($changedData->getRows(), true);
        }
        $logbook->addDataSheet('WorkingSheet-' . $groupId, $loadedData);

        // Determine where to insert new elements.
        $insertOnTop = ! $this->getAppendToEnd();
        if ($insertOnTop) {
            $insertionIndex = $this->getOrderStartsWith() - 1;
            $logbook->addLine('New elements will be inserted at the start' . ' with index ' . $this->getOrderStartsWith());
        } else {
            $insertionIndex = max($loadedData->getColumnValues($indexingAlias));
            if ($insertionIndex !== "") {
                $insertionIndex++;
                $logbook->addLine('New elements will be inserted at the end with index ' . $insertionIndex);
            } else {
                $insertionIndex = $this->getOrderStartsWith();
                $logbook->addLine('Could not determine insertion index, new elements will be inserted at index ' . $insertionIndex . ' by default.');
            }
        }

        // Post-Process rows.
        foreach ($loadedData->getRows() as $rowNumber => $siblingRow) {
            // Fill missing indices.
            $index = $siblingRow[$indexingAlias];
            if ($index === null || $index === "") {
                $logbook->addLine('Inserting row ' . $rowNumber . ' with UID ' . $siblingRow[$uidAlias] . ' at ' . $insertionIndex);
                // Update sheet.
                $loadedData->setCellValue($indexingAlias, $rowNumber, $insertionIndex);
                // Record change.
                $siblingRow[$indexingAlias] = $insertionIndex;
                $pendingChanges[$siblingRow[$uidAlias]] = $siblingRow;
            }

            // Add row as node to cache.
            $cache->addElement($siblingRow[$uidAlias], $parents);
        }

        // Save sibling datasheet to cache.
        $sorters = new DataSorterList($loadedData->getWorkbench(), $loadedData);
        $sorters->addFromString($indexingAlias);
        $loadedData->sort($sorters, false);
        
        try {
            $siblingData = [
                'siblings' => $loadedData,
                'priority' => $changedData
            ];
            $cache->setData($row[$uidAlias], $siblingData);
        } catch (InvalidArgumentException $exception) {
            throw new BehaviorRuntimeError($this, 'Could not add data to cache!', null, $exception, $logbook);
        }

        $logbook->addLine('Found ' . ($loadedData->countRows()) . ' elements in group ' . $groupId . '.');
        $logbook->addDataSheet('Group-' . $groupId, $loadedData);
        $logbook->addIndent(-1);

        return $siblingData;
    }

    /**
     * Creates a filter setup, that groups data by its parents. The result is not an EXACT grouping (see below).
     * Effort is quadratic with respect to the number of parent aliases.
     *
     * NOTE: With strict filter the groupings would be sensitive to the order in which the parents appear.
     * For example: Parents [A,B] would be a separate group from Parents [B,A].
     * To prevent this, we check ALL parent columns for matches. This will return false
     * positives, which you will have to check for manually (Parents [A,NULL,NULL] would for instance match with
     * Parents [A,B,NULL]).
     *
     * @param DataSheetInterface $dataSheet
     * @param array              $row
     * @return array
     */
    // TODO geb 2024-10-11: This function is meant as an optimization for large data sets. The idea is to reduce the
    // TODO amount of data retrieved by the SQL-Query. This might not be a good idea, since this filter scheme has O(n²) with
    // TODO respect to the number of parent aliases. Alternatively we might do a simple filter for just one parent or none at all.
    // TODO Any filtering not done via SQL must be done in code, which is easier to do, but may incur long load times for large data sets.
    private function addFiltersFromParentAliases(DataSheetInterface $dataSheet, array $row): array
    {
        // Prepare variables.
        $parents = [];
        $metaObject = $dataSheet->getMetaObject();
        $parentAliases = $this->getParentAliases();
        $conditionGroup = ConditionGroupFactory::createAND($metaObject);

        foreach ($parentAliases as $parentAlias) {
            // Get parent information.
            $parent = $row[$parentAlias];
            // If we already created filters for this specific parent, we can skip it.
            if (in_array($parent, $parents)) {
                $parents[] = $parent;
                continue;
            }
            $parents[] = $parent;

            // Create a filter that checks across all parent columns, whether at least one of them matches our parent.
            $subGroup = ConditionGroupFactory::createOR($metaObject);
            foreach ($parentAliases as $columnToCheck) {
                if ($parent === null) {
                    // If the parent information is null, we need to add a special filter.
                    $subGroup->addConditionFromString($columnToCheck, EXF_LOGICAL_NULL, ComparatorDataType::EQUALS);
                } else {
                    // Otherwise we add a regular filter.
                    $subGroup->addConditionFromString(
                        $columnToCheck,
                        $parent,
                        ComparatorDataType::EQUALS,
                        false);
                }
            }
            // Add the sub-filter to the overall filter.
            $conditionGroup->addNestedGroup($subGroup);
        }

        // Apply filters.
        $dataSheet->getFilters()->addNestedGroup($conditionGroup);
        return $parents;
    }

    /**
     * Checks, whether a given row belongs to a group of siblings.
     *
     * A row belongs to a group, if it has the exact same parents, regardless
     * of the order they appear in. This includes multiples of the same parent.
     * Parents [A,B,B] belongs to the same group as Parents [B,A,B], but
     * Parents [A,A,B] does not.
     *
     * @param array $row
     * @param array $parents
     * @return bool
     */
    private function belongsToGroup(array $row, array $parents): bool
    {
        $matchedIndices = [];

        foreach ($this->getParentAliases() as $parentAlias) {
            $parent = $row[$parentAlias];
            $matched = false;

            foreach ($parents as $index => $parentToMatch) {
                // Skip indices that already produced a match.
                if (in_array($index, $matchedIndices)) {
                    continue;
                }

                if ($parent === $parentToMatch) {
                    $matched = true;
                    $matchedIndices[] = $index;
                    break;
                }
            }

            // If any parent could not be matched (even if it was a duplicate), the row does not belong to the group.
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * Finds all indices that need to be changed to successfully update the ordering.
     *
     * @param array           $siblingData
     * @param array           $pendingChanges
     * An array to which any changes necessary to achieve the desired ordering will be appended.
     * @param BehaviorLogBook $logBook
     */
    private function findPendingChanges(
        array $siblingData, 
        array &$pendingChanges,
        BehaviorLogBook $logBook) : void
    {
        // Prepare variables.
        $indexCache = [];
        $siblingSheet = $siblingData['siblings'];
        $priorityUIds = $siblingData['priority']->getUidColumn()->getValues();
        $indexAlias = $this->getOrderNumberAttributeAlias();
        $uidAlias = $siblingSheet->getUidColumnName();
        $closeGaps = $this->getCloseGaps();
        $countInsertedElements = count($pendingChanges);
        
        $logBook->addLine('Scanning for pending changes...');
        $logBook->addIndent(1);
        $logBook->addLine('Changes pending from inserting new elements: ' . $countInsertedElements . '.');

        //  We need to sort elements in two stages:
        //  First, any changes made by the user are prioritized, so they are sorted first
        //  to figure out what slots are left available for low priority items.
        //
        //  Database        1,2,3,4,5
        //  Priority        2.,2.,4.,4.
        //  SiblingSheet    1,2.,2.,2,3,4.,4.,4,5
        //  -------------PRIO----------------
        //  Index   Next    Result  NewNext Cache
        //  2       2       2       3       2 => 3
        //  2       3       3       4       2 => 4
        //  4       4       4       5       4 => 5
        //  4       5       5       6       4 => 6
        //  SiblingSheet    1,2.,2,3.,3,4.,4,5.,5   Cache [2 => 6]
        //  -------------REGL----------------
        //  Index   Next    Result  NewNext Shifted
        //  1       1       1       2       6
        //  2       6       6       7       No
        //  3       7       7       8       No
        //  4       8       8       9       No
        //  5       9       9       10      No
        // Result 1,2.,3.,4.,5.,6,7,8,9
        
        // Priority pass.
        $nextIndex = -1;
        $indexingCache = [];
        foreach ($siblingSheet->getRows() as $row) {
            if(!in_array($row[$uidAlias], $priorityUIds)) {
                continue;
            }
            // Get current index of row.
            $index = $row[$indexAlias];
            $this->validateIndex($index, $indexAlias, $logBook);
            
            // Update index and commit row to pending.
            $row[$indexAlias] = max($index, $nextIndex);
            $pendingChanges[$row[$uidAlias]] = $row;
            // Update next index.
            $nextIndex = $row[$indexAlias] + 1;
            // Update indexing cache.
            if(key_exists($index, $indexingCache)) {
                $indexingCache
            }
            $indexingCache[$index] = $nextIndex;
        }
        
        // Regular pass.
        foreach ($siblingSheet->getRows() as $row) {
            // Get current index of row.
            $index = $row[$indexAlias];
            $this->validateIndex($index, $indexAlias, $logBook);

            // `$nextIndex` always points to the next open slot. If `$index` is smaller or equal to that, a change is required.
            // Otherwise, a change is required only if gap closing is enabled.
            if ($index <= $nextIndex || $closeGaps) {
                if ($index != $nextIndex) {
                    // Mark row as pending.
                    $row[$indexAlias] = $index = $nextIndex;
                    $pendingChanges[$row[$uidAlias]] = $row;
                }
            }

            // Update next index.
            $nextIndex = $index + 1;
        }
        
        $logBook->addLine('Pending changes detected in this step: ' . (count($pendingChanges) - $countInsertedElements) . '.');
        $logBook->addIndent(1);
    }

    /**
     * @param mixed           $index
     * @param string          $indexAlias
     * @param BehaviorLogBook $logBook
     * @return void
     */
    private function validateIndex(mixed $index, string $indexAlias, BehaviorLogBook $logBook) : void
    {
        if (!is_numeric($index)) {
            throw new BehaviorRuntimeError(
                $this,
                'Cannot order values of attribute "' . $indexAlias . '": invalid value "' . $index . "' encountered! Ordering indices must be numeric.",
                null,
                null,
                $logBook);
        }
    }
    
    /**
     *
     * @param DataSheetInterface $sheet
     * @param bool               $removeRows
     * @param bool               $removeCols
     * @return DataSheetInterface
     */
    private function createEmptyCopy(DataSheetInterface $sheet, bool $removeRows, bool $removeCols): DataSheetInterface
    {
        $emptyCopy = $sheet->copy();

        if ($removeRows) {
            $emptyCopy->removeRows();
        }

        if ($removeCols) {
            $emptyCopy->getColumns()->removeAll();
        }

        return $emptyCopy;
    }

    /**
     *
     * @param DataSheetInterface $eventSheet
     * @param array              $pendingChanges
     * @param BehaviorLogBook    $logBook
     * @return void
     */
    private function applyChanges(
        DataSheetInterface $eventSheet,
        array              $pendingChanges,
        BehaviorLogBook    $logBook): void
    {
        $uidAlias = $eventSheet->getUidColumnName();
        $indexAlias = $this->getOrderNumberAttributeAlias();
        $updateSheet = $this->createEmptyCopy($eventSheet, true, false);

        foreach ($pendingChanges as $pending) {
            if (($rowToChange = $eventSheet->getUidColumn()->findRowByValue($pending[$uidAlias])) !== false) {
                $eventSheet->setCellValue($indexAlias, $rowToChange, $pending[$indexAlias]);
            } else {
                $updateSheet->addRow($pending);
            }
        }
        
        $logBook->addLine('Generated datasheet with pending changes.');
        $logBook->addDataSheet('PendingChanges', $updateSheet);
        
        $backupSheet = $updateSheet->copy();
        $backupSheet->getColumns()->removeAll();
        $existingColumns = $updateSheet->getColumns();
        
        $logBook->addLine('Generating backup sheet...');
        $logBook->addIndent(1);
        foreach ($backupSheet->getMetaObject()->getAttributes() as $attribute) {
            $colAlias = $attribute->getAlias();
            if(!$existingColumns->getByExpression($colAlias)) {
                $logBook->addLine('Added backup for attribute ' . $colAlias . '.');
                $backupSheet->getColumns()->addFromExpression($colAlias);
            }
        }
        $backupSheet->getFilters()->addConditionFromValueArray($uidAlias, $updateSheet->getColumnValues($uidAlias));
        $backupSheet->dataRead();
        
        $logBook->addIndent(1);
        $logBook->addLine('Generated backup sheet.');
        $logBook->addDataSheet('Backup', $backupSheet);
        
        $outputSheet = $backupSheet->merge($updateSheet);
        $logBook->addLine('Generated output sheet.');
        $logBook->addDataSheet('Output', $outputSheet);
        
        try {
            $outputSheet->dataSave();
        } catch (DataSheetExceptionInterface $exception) {
            throw new BehaviorRuntimeError($this, 'Failed to apply changes!', null, $exception, $logBook);
        }
        
        $logBook->addLine('Successfully applied changes.');
    }

    /**
     * Start order with this number - typically `1` or `0`
     * 
     * A starting index of `1` for example represents an intuitive count from
     * `1` to infinity.
     * 
     * @uxon-property order_starts_with
     * @uxon-type integer
     * @uxon-default 1
     * 
     * @param int $value
     * @return OrderingBehavior
     */
    protected function setOrderStartsWith(int $value): OrderingBehavior
    {
        $this->startIndex = $value;
        return $this;
    }

    /**
     * @deprecated use setOrderStartsWith() / order_starts_with instead
     * @param int $value
     * @return \exface\Core\Behaviors\OrderingBehavior
     */
    protected function setStartingIndex(int $value): OrderingBehavior
    {
        return $this->setOrderStartsWith($value);
    }

    /**
     *
     * @return int
     */
    protected function getOrderStartsWith(): int
    {
        return $this->startIndex;
    }

    /**
     * Set to FALSE to leave gaps in the order number if an element is deleted from the middle
     * 
     * If TRUE an order of `1 => "A", 2 => "B", 4 => "C", 6 => "D"` would be rearranged to
     * `1 => "A", 2 => "B", 3 => "C", 4 => "D"`.
     * 
     * @uxon-property close_gaps
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return OrderingBehavior
     */
    protected function setCloseGaps(bool $trueOrFalse): OrderingBehavior
    {
        $this->closeGaps = $trueOrFalse;
        return $this;
    }

    /**
     *
     * @return bool
     */
    protected function getCloseGaps(): bool
    {
        return $this->closeGaps;
    }

    /**
     * Toggle whether elements without indices should be inserted at the top or bottom of the order.
     * 
     * @uxon-property append_to_end
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return OrderingBehavior
     */
    protected function setAppendToEnd(bool $trueOrFalse): OrderingBehavior
    {
        $this->insertWithMaxIndex = $trueOrFalse;
        return $this;
    }

    /**
     * @deprecated use setAppendToEnd() / new_element_on_top instead
     * @param bool $trueOrFalse
     * @return \exface\Core\Behaviors\OrderingBehavior
     */
    protected function setNewElementOnTop(bool $trueOrFalse): OrderingBehavior
    {
        return $this->setAppendToEnd($trueOrFalse);
    }

    /**
     *
     * @return bool
     */
    protected function getAppendToEnd(): bool
    {
        return $this->insertWithMaxIndex;
    }

    /**
     *
     * @return array
     */
    protected function getParentAliases(): array
    {
        return $this->orderBoundaryAliases;
    }

    /**
     * Define from which columns the behavior should determine the parents of a row.
     * 
     * @uxon-property order_within_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * @uxon-required true
     * 
     * @param UxonObject $value
     * @return OrderingBehavior
     */
    protected function setOrderWithinAttributes(UxonObject $value): OrderingBehavior
    {
        $this->orderBoundaryAliases = $value->toArray();
        return $this;
    }

    /**
     * @deprecated use setOrderWithinAttributes() / order_within_attributes instead
     * 
     * @param \exface\Core\CommonLogic\UxonObject $value
     * @return \exface\Core\Behaviors\OrderingBehavior
     */
    protected function setIndexingBoundaryAttributes(UxonObject $value) : OrderingBehavior
    {
        return $this->setOrderWithinAttributes($value);
    }

    /**
     * @deprecated use setParentAliases() / parent_aliases instead
     * 
     * @param \exface\Core\CommonLogic\UxonObject $value
     * @return \exface\Core\Behaviors\OrderingBehavior
     */
    protected function setParentAliases(UxonObject $value) : OrderingBehavior
    {
        return $this->setOrderWithinAttributes($value);
    }

    /**
     *
     * @return string
     */
    protected function getOrderNumberAttributeAlias() : string
    {
        return $this->orderAttributeAlias;
    }

    /**
     * The attribute to store the order number
     * 
     * @uxon-property order_number_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return OrderingBehavior
     */
    protected function setOrderNumberAttribute(string $value): OrderingBehavior
    {
        $this->orderAttributeAlias = $value;
        return $this;
    }

    /**
     * @deprecated use setOrderAttribute() instead!
     * 
     * @param string $value
     * @return OrderingBehavior
     */
    protected function setIndexAlias(string $value): OrderingBehavior
    {
        return $this->setOrderNumberAttribute($value);
    }

    /**
     * @deprecated  use setOrderAttribute() / order_number_attribute instead
     * 
     * @param string $alias
     * @return \exface\Core\Behaviors\OrderingBehavior
     */
    protected function setOrderIndexAttribute(string $alias) : OrderingBehavior
    {
        return $this->setOrderNumberAttribute($alias);
    }
}