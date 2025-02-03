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
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 * Orders data rows automatically and saves the order number in one of the attributes.
 * 
 * This behavior orders all data within configured boundaries: e.g.
 * 
 * - all pages (`exface.Core.PAGE`) within a `MENU_PARENT` 
 * - all order positions within an `ORDER`
 * 
 * Every time a row of the ordered object is saved, this behavior finds all rows with matching values in their
 * `order_with_attributes` and orders them based on your configuration. For example, if you update a page, this
 * behavior would order every page that has the same `MENU_PARENT`.
 * 
 * ## Configuration
 * 
 * - `order_number_attribute` (required) - save the order number here
 * - `order_with_attributes` - all rows with the same value of these attributes will share an ordering sequence.
 * - `order_starts_with` - ordering sequences will start with this index.
 * - `close_gaps` - by default, deleting a row in the middle of the sequence will update order numbers of subsequent 
 * rows to close the gap. You can explicitly allow gaps by setting this option to `false`. 
 * - `append_to_end` - set to `false` to insert rows without a valid value for their `order_number_attribute` at the
 * start of their sequence, i.e. the value of `order_starts_with`.
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
    private const FLAG_PROCESSED = "PROCESSED";
    
    // internal variables
    private bool $working = false;

    // configurable variables
    private int $startIndex = 1;
    private bool $closeGaps = true;
    private bool $insertWithMaxIndex = true;
    private mixed $orderAttributeAlias = null;
    private mixed $orderBoundaryAliases = [];
    
    private array $oldDataCache = [];

    /**
     *
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners(): BehaviorInterface
    {
        $priority = $this->getPriority();

        $onCacheOldDataHandle = array($this, 'onCacheOldData');
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), $onCacheOldDataHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), $onCacheOldDataHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), $onCacheOldDataHandle, $priority);

        $determineOrderHandle = array($this, 'onHandleDetermineOrder');
        $this->getWorkbench()->eventManager()->addListener(OnCreateDataEvent::getEventName(), $determineOrderHandle, $priority);
        //$this->getWorkbench()->eventManager()->addListener(OnUpdateDataEvent::getEventName(), $determineOrderHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), $determineOrderHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnDeleteDataEvent::getEventName(), $determineOrderHandle, $priority);

        return $this;
    }

    /**
     *
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners(): BehaviorInterface
    {
        $onCacheOldDataHandle = array($this, 'onCacheOldData');
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeCreateDataEvent::getEventName(), $onCacheOldDataHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), $onCacheOldDataHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), $onCacheOldDataHandle);

        $determineOrderHandle = array($this, 'onHandleDetermineOrder');
        $this->getWorkbench()->eventManager()->removeListener(OnCreateDataEvent::getEventName(), $determineOrderHandle);
        //$this->getWorkbench()->eventManager()->removeListener(OnUpdateDataEvent::getEventName(), $determineOrderHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), $determineOrderHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnDeleteDataEvent::getEventName(), $determineOrderHandle);

        return $this;
    }

    /**
     * Cache old data (from the database) for later use.
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function onCacheOldData(DataSheetEventInterface $event) : void
    {
        // Ignore MetaObjects we are not associated with.
        if (!$event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        if($this->working) {
            return;
        }
        $this->working = true;
        
        $logBook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logBook));
        
        $logBook->addLine('Caching old data for use in later steps.');
        $eventSheet = $event->getDataSheet();
        $this->fetchMissingColumns($eventSheet,$logBook);
        
        $loadedData = $this->createEmptyCopy($eventSheet, true, false);
        $loadedData->setFilters(ConditionGroupFactory::createOR($loadedData->getMetaObject()));
        foreach ($eventSheet->getRows() as $row) {
            $this->addFiltersFromParentAliases($loadedData, $row);
        }
        
        $loadedData->dataRead();
        $this->oldDataCache[] = [
            'eventSheet' => $eventSheet,
            'oldDataSheet' => $loadedData
        ];
        
        $logBook->addLine('Data successfully cached.');
        $logBook->addDataSheet('Cache', $loadedData);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logBook));
        $this->working = false;
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

        // Order data.
        $pendingChanges = [];
        $onDelete = $event instanceof OnBeforeDeleteDataEvent || $event instanceof OnDeleteDataEvent;
        $this->groupAndOrderDataByParents($onDelete, $eventSheet, $pendingChanges, $logbook);

        if(empty($pendingChanges)) {
            $logbook->addLine('No changes pending for input data.');
        } else {
            $logbook->addLine('Found ' . count($pendingChanges) . ' pending changes for input data.');
            $this->applyChanges($event, $pendingChanges, $logbook);
        }
        
        // Finish work.
        $this->working = false;
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    /**
     * Load any missing parent columns and merges their data into the event sheet.
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
     * Any necessary data updates will be appended to this array, since updating is deferred to `On...DataEvent`.
     * @param BehaviorLogBook    $logbook
     * @return void
     */
    private function groupAndOrderDataByParents(
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
            $logbook->addIndent(1);
            $siblingData = $this->getSiblings($onDelete, $eventSheet, $pendingChanges, $row, $indexAlias, $cache, $logbook);

            // If we already ordered the group this row belongs to, continue.
            if ($siblingData === self::FLAG_PROCESSED) {
                $logbook->addLine('Data for this group has already been processed, moving on.');
                $logbook->addIndent(-1);
                continue;
            }

            // Find and record changes.
            $this->findPendingChanges($siblingData, $pendingChanges, $logbook);

            // Mark all siblings as PROCESSED, to avoid ordering the same group multiple times.
            try {
                $cache->setData($siblingData['siblings']->getRow(0)[$uidAlias], self::FLAG_PROCESSED);
            } catch (InvalidArgumentException $exception) {
                throw new BehaviorRuntimeError($this, 'Could mark data as "' .  self::FLAG_PROCESSED .'": ' . $exception->getMessage(), null, $exception, $logbook);
            }
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
     * Any necessary data updates will be appended to this array, since updating is deferred to `On...DataEvent`.
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
        // Check if we already loaded sibling data for this UID before.
        $uidAlias = $eventSheet->getUidColumnName();
        if ($siblingData = $cache->getData($row[$uidAlias])) {
            $logbook->addLine('Sibling data retrieved from cache.');
            return $siblingData;
        }

        // Retrieve old data from cache.
        $loadedData = null;
        foreach ($this->oldDataCache as $oldData) {
            if($oldData['eventSheet'] === $eventSheet) {
                $loadedData = $oldData['oldDataSheet']->copy();
                break;
            }
        }
        if($loadedData === null) {
            throw new BehaviorRuntimeError($this, 'Could not retrieve old data from cache!', null,null, $logbook);
        }
        
        $parents = $this->addFiltersFromParentAliases($loadedData, $row);
        $groupId = json_encode($parents);
        
        $logbook->addLine('Looking for other elements in group ' . $groupId . '.');
        // Remove any rows that do not belong to this group.
        foreach ($loadedData->getRows() as $loadedRow) {
            $siblingUId = $loadedRow[$uidAlias];
            if (!$this->belongsToGroup($loadedRow, $parents)) {
                $loadedData->removeRowsByUid($siblingUId);
            }
        }
        // Extract rows from event sheet that belong to this group.
        $changedData = $this->createEmptyCopy($eventSheet, true, false);
        foreach ($eventSheet->getRows() as $changedRow) {
            $changedUId = $changedRow[$uidAlias];
            $loadedRow = $loadedData->getRowByColumnValue($uidAlias, $changedUId);
            if($this->belongsToGroup($changedRow, $parents)) {
                // On delete, all changed rows will be removed from the database,
                // so we have to remove them from our ordering data as well.
                if($onDelete) {
                    $loadedData->removeRowsByUid($changedUId);
                    $cache->addElement($changedUId, $parents);
                } 
                
                if ($loadedRow === null || $loadedRow[$indexingAlias] !== $changedRow[$indexingAlias]) {
                    $changedData->addRow($changedRow, true, false);
                }
            }
        }

        if($onDelete) {
            $rowCount = $loadedData->countRows();
            if($loadedData->countRows() === 0) {
                $logbook->addLine('All elements of this group have been deleted.');
                $cache->setData($row[$uidAlias], self::FLAG_PROCESSED);
                return self::FLAG_PROCESSED;
            } else {
                $logbook->addLine($rowCount . ' left to order, after excluding deleted data. Using row ' . $row[$uidAlias] . ' as reference.');
                $row = $loadedData->getRow();
            }
        } else {
            $logbook->addLine('Fetching new data from changed rows.');
            foreach ($changedData->getRows() as $row) {
                $rowNr = $loadedData->getUidColumn()->findRowByValue($row[$uidAlias]);
                if($rowNr !== false) {
                    $loadedData->setCellValue($indexingAlias, $rowNr, $row[$indexingAlias]);
                } else {
                    $loadedData->addRow($row);
                }
            }
        }
        $logbook->addDataSheet('WorkingSheet-' . $groupId, $loadedData);

        // Determine where to insert new elements.
        $insertOnTop = ! $this->getAppendToEnd();
        if ($insertOnTop) {
            $insertionIndex = $this->getOrderStartsWith();
            $logbook->addLine('New elements will be inserted at the start with index ' . $this->getOrderStartsWith());
        } else {
            $insertionIndex = max($loadedData->getColumnValues($indexingAlias));
            if ($insertionIndex !== "") {
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
                // Ensure that this row will have sorting priority.
                $changedData->addRow($siblingRow, true);
            }

            // Add row as node to cache.
            $cache->addElement($siblingRow[$uidAlias], $parents);
        }

        // Sort sheet by ordering index.
        $sorters = new DataSorterList($loadedData->getWorkbench(), $loadedData);
        $sorters->addFromString($indexingAlias);
        $loadedData->sort($sorters, false);
        
        // Save sibling datasheet to cache.
        try {
            $siblingData = [
                'siblings' => $loadedData,
                'priority' => $changedData
            ];
            $cache->setData($row[$uidAlias], $siblingData);
        } catch (InvalidArgumentException $exception) {
            throw new BehaviorRuntimeError($this, 'Could not add data to cache: ' . $exception->getMessage(), null, $exception, $logbook);
        }

        $logbook->addLine('Found ' . ($loadedData->countRows()) . ' elements in group ' . $groupId . '.');
        $logbook->addDataSheet('Group-' . $groupId, $loadedData);

        return $siblingData;
    }

    /**
     * Creates a filter setup, that groups data by its parents. The result is not an EXACT grouping (see below).
     * Effort is quadratic with respect to the number of parent aliases.
     *
     * NOTE: This is not a strict filter! With a strict filter the groupings would be sensitive to the order in which
     * the parents appear. For example: Parents [A,B] would be a different group than Parents [B,A]. Users would find
     * this confusing, which is why we have to make sure that groupings are detected irrespective of the order in which
     * the parents appear. TO do this we check ALL parent columns for matches.  The resulting data will contain false
     * positives for example [A,NULL] would be grouped with [A,B]. We solve this issue by manually filtering the
     * retrieved data set in a later step.
     * 
     * @see OrderingBehavior::belongsToGroup()
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
     * A row belongs to a group, if it has the exact same parents as the group, regardless
     * of the order they appear in. 
     * 
     * For example, Parents [A,B,B] (2x A, 1x B) belongs to the same group as Parents [B,A,B], but not
     * Parents [A,A,B].
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
        $siblingSheet = $siblingData['siblings'];
        $priorityUIds = $siblingData['priority']->getUidColumn()->getValues();
        $indexAlias = $this->getOrderNumberAttributeAlias();
        $uidAlias = $siblingSheet->getUidColumnName();
        $countInsertedElements = count($pendingChanges);
        $indexingCache = [];
        
        $logBook->addLine('Scanning for pending changes...');

        //  We need to sort elements in two stages:
        //  First, any changes made by the user are prioritized, so they are sorted first
        //  to figure out what slots are left available for low priority items.
        //
        //  Database        1,2,3,4,5
        //  Priority        2.,2.,4.,4.
        //  SiblingSheet    1,2.,2.,2,3,4.,4.,4,5
        //  -------------PRIORITY----------------
        //  Index   Next    Result  NewNext Cache
        //  2       2       2       3       2 => 3
        //  2       3       3       4       2 => 4 ] <- STITCH CACHE
        //  4       4       4       5       4 => 5 ]
        //  4       5       5       6       4 => 6
        //  SiblingSheet    1,2.,2,3.,3,4.,4,5.,5
        //  indexingCache [2 => 6]
        //  -------------REGULAR-----------------
        //  Index   Next    Result  NewNext Shifted
        //  1       1       1       2       6
        //  2       6       6       7       No
        //  3       7       7       8       No
        //  4       8       8       9       No
        //  5       9       9       10      No
        // Result 1,2.,3.,4.,5.,6,7,8,9
        
        // Priority pass.
        if(count($priorityUIds) > 0) {
            $nextIndex = $this->getOrderStartsWith();
            foreach ($siblingSheet->getRows() as $row) {
                // In this pass, we only process elements that have been changed by the user.
                // Their indices have priority over unchanged data to satisfy user expectations.
                if(!in_array($row[$uidAlias], $priorityUIds)) {
                    continue;
                }
                // Get current index of row.
                $index = $row[$indexAlias];
                $this->validateIndex($index, $indexAlias, $logBook);
                // Update index and commit row to pending.
                $row[$indexAlias] = max($index, $nextIndex);
                $siblingSheet->addRow($row, true);
                $pendingChanges[$row[$uidAlias]] = $row;

                // Update next index.
                $nextIndex = $row[$indexAlias] + 1;
                // Update indexing cache.
                $indexingCache[$index] = $nextIndex;
            }
            
            // Stitch indexing cache.
            foreach ($indexingCache as $from => $to) {
                if(key_exists($to, $indexingCache)) {
                    $indexingCache[$from] = $indexingCache[$to];
                    unset($indexingCache[$to]);
                }
            }
        }
        
        // Regular pass.
        $closeGaps = $this->getCloseGaps();
        $nextIndex = $this->getOrderStartsWith();
        foreach ($siblingSheet->getRows() as $row) {
            // Get current index of row.
            $index = $row[$indexAlias];
            $this->validateIndex($index, $indexAlias, $logBook);
            // Check indexingCache.
            if(key_exists($index, $indexingCache)) {
                $nextIndex = $indexingCache[$index];
                unset($indexingCache[$index]);
            }
            
            $isPriorityRow = in_array($row[$uidAlias], $priorityUIds);
            $regularUpdate = $index < $nextIndex && !$isPriorityRow;
            $closeGapUpdate = $index > $nextIndex && $closeGaps;
            if($regularUpdate || $closeGapUpdate) {
                $row[$indexAlias] = $nextIndex;
                $pendingChanges[$row[$uidAlias]] = $row;
            }

            // Update next index. We can skip priority rows, because they have already been processed
            // earlier, unless their index was changed to close a gap.
            if(!$isPriorityRow || $closeGapUpdate) {
                $nextIndex = $row[$indexAlias] + 1;
            }
        }
        
        $logBook->addLine('Pending changes detected in this step: ' . (count($pendingChanges) - $countInsertedElements) . '.');
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
     * @param DataSheetEventInterface $event
     * @param array                   $pendingChanges
     * @param BehaviorLogBook         $logBook
     * @return void
     */
    private function applyChanges(
        DataSheetEventInterface $event,
        array                   $pendingChanges,
        BehaviorLogBook         $logBook): void
    {
        // Generate update sheet.
        $eventSheet = $event->getDataSheet();
        $updateSheet = $this->createEmptyCopy($eventSheet, true, false);
        foreach ($pendingChanges as $pending) {
            $updateSheet->addRow($pending);
        }
        if(!$event instanceof OnDeleteDataEvent && !$event instanceof OnBeforeUpdateDataEvent) {
            // Make sure any data we didn't modify is up-to-date.
            $currentSheet = $eventSheet->copy();
            $currentSheet->getColumns()->removeByKey($this->getOrderNumberAttributeAlias());
            $updateSheet->addRows($currentSheet->getRows(), true, false);
        }
        
        $logBook->addLine('Generated datasheet with pending changes.');
        $logBook->addDataSheet('PendingChanges', $updateSheet);
        
        // In order to avoid collisions, we first need to shift all changed rows out of the way.
        // We do so by mirroring their index and ensuring they are outside our ordering range.
        $shiftSheet = $updateSheet->copy();
        $indexAlias = $this->getOrderNumberAttributeAlias();
        $safeIndex = $this->getOrderStartsWith() - 1;
        foreach ($shiftSheet->getRows() as $rowNr => $row) {
            $shiftSheet->setCellValue($indexAlias, $rowNr, -$row[$indexAlias] + $safeIndex);
        }
        
        try {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $shiftSheet->dataSave($transaction);
            $shiftSheet->getColumns()->removeByKey($indexAlias);
            $updateSheet->addRows($shiftSheet->getRows(), true, false);
            $updateSheet->dataSave($transaction);
        } catch (DataSheetExceptionInterface $exception) {
            throw new BehaviorRuntimeError($this, 'Failed to apply changes: ' . $exception->getMessage(), null, $exception, $logBook);
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