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
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Events\DataSheetTransactionEventInterface;
use exface\Core\Interfaces\Events\EventInterface;
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
    private array $eventCache = [];

    // configurable variables
    private int $startIndex = 1;
    private bool $closeGaps = true;
    private bool $insertWithMaxIndex = true;
    private mixed $orderAttributeAlias = null;
    private mixed $orderBoundaryAliases = [];
    

    /**
     *
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners(): BehaviorInterface
    {
        $priority = $this->getPriority();

        $onBeforeHandle = array($this, 'handleOnBefore');
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), $onBeforeHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), $onBeforeHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), $onBeforeHandle, $priority);

        $onAfterHandle = array($this, 'handleOnAfter');
        $this->getWorkbench()->eventManager()->addListener(OnCreateDataEvent::getEventName(), $onAfterHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnUpdateDataEvent::getEventName(), $onAfterHandle, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnDeleteDataEvent::getEventName(), $onAfterHandle, $priority);
        
        return $this;
    }

    /**
     *
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners(): BehaviorInterface
    {
        $onBeforeHandle = array($this, 'handleOnBefore');
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeCreateDataEvent::getEventName(), $onBeforeHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), $onBeforeHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), $onBeforeHandle);

        $onAfterHandle = array($this, 'handleOnAfter');
        $this->getWorkbench()->eventManager()->removeListener(OnCreateDataEvent::getEventName(), $onAfterHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnUpdateDataEvent::getEventName(), $onAfterHandle);
        $this->getWorkbench()->eventManager()->removeListener(OnDeleteDataEvent::getEventName(), $onAfterHandle);

        return $this;
    }

    protected function beginWork(EventInterface $event): BehaviorLogBook|bool
    {
        if (!$event instanceof DataSheetEventInterface ||
            !$event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return false;
        }
        
        return parent::beginWork($event);
    }


    /**
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function handleOnBefore(DataSheetEventInterface $event) : void
    {
        if(!$logbook = $this->beginWork($event)) {
            return;
        }

        $eventSheet = $event->getDataSheet();
        $logbook->addDataSheet('InputData', $eventSheet->copy());
        
        // Make sure it has a UID column.
        if ($eventSheet->hasUidColumn() === false) {
            $logbook->addLine('Cannot order objects with no Uid attribute.');
            $this->finishWork($event, $logbook);
            return;
        }
        
        if(!$event instanceof DataSheetTransactionEventInterface) {
            throw new BehaviorRuntimeError($this, 'Event ' . $event->getAlias() . ' not supported!', null, null, $logbook);
        }
        
        // Fetch any missing columns.
        $this->fetchMissingColumns($eventSheet,$logbook);
        // Load data for groups present in event sheet.
        $loadedData = $this->createEmptyCopy($eventSheet, true, false);
        $loadedData->setFilters(ConditionGroupFactory::createOR($loadedData->getMetaObject()));
        foreach ($eventSheet->getRows() as $row) {
            $this->addFiltersFromParentAliases($loadedData, $this->getParentsForRow($row));
        }
        $loadedData->dataRead();
        
        $logbook->addLine('Data successfully loaded from database.');
        $logbook->addDataSheet('LoadedData', $loadedData->copy());
        
        // Order data.
        $pendingChanges = [];
        $siblingCache = new LazyHierarchicalDataCache();
        $this->groupAndOrderDataByParents($event, $loadedData, $siblingCache, $pendingChanges, $logbook);

        if(empty($pendingChanges)) {
            $logbook->addLine('No changes pending for input data.');
            $shiftedIndices = [];
        } else {
            $logbook->addLine('Found ' . count($pendingChanges) . ' pending changes.');
            $shiftedIndices = $this->shiftIndices($eventSheet, $siblingCache, $pendingChanges, $logbook);
            
            $logbook->addLine('Shifted indices to make room for next step');
            $logbook->addDataSheet('Shifted', $eventSheet);
        }
        
        // Cache data for next step.
        $this->eventCache[] = [
            'eventSheet' => $eventSheet,
            'loadedData' => $loadedData,
            'shiftedIndices' => $shiftedIndices
        ];
        
        $this->finishWork($event, $logbook);
    }
    
    /**
     * @param DataSheetEventInterface $event
     */
    public function handleOnAfter(DataSheetEventInterface $event): void
    {
        // Prevent recursion.
        if (!$logbook = $this->beginWork($event)) {
            return;
        }
        
        // Get datasheet.
        $eventSheet = $event->getDataSheet();
        $logbook->addDataSheet('InputData', $eventSheet);
        
        // Make sure it has a UID column.
        if ($eventSheet->hasUidColumn() === false) {
            $logbook->addLine('Cannot order objects with no Uid attribute.');
            $this->finishWork($event, $logbook);
            return;
        }

        $eventCache = null;
        foreach ($this->eventCache as $cachedEventData) {
            if($cachedEventData['eventSheet'] === $eventSheet) {
                $eventCache = $cachedEventData;
                break;
            }
        }
        
        if($eventCache === null) {
            throw new BehaviorRuntimeError($this, 'Could not load data from cache!', null, null, $logbook);
        }
        
        // Restore shifted indices. This is needed to restore indices of rows that did not
        // have a UID in the last step.
        $indexAlias = $this->getOrderNumberAttributeAlias();
        $shiftedIndices = $eventCache['shiftedIndices'];

        foreach ($eventSheet->getRows() as $rowNr => $row) {
            $restoredIndex = $shiftedIndices[$row[$indexAlias]];
            if($restoredIndex !== null) {
                $row[$indexAlias] = $restoredIndex;
                $eventSheet->addRow($row, true);
            }
        }
        
        $pendingChanges = [];
        $siblingCache = new LazyHierarchicalDataCache();
        $this->groupAndOrderDataByParents($event, $eventCache['loadedData'], $siblingCache, $pendingChanges, $logbook);

        if(empty($pendingChanges)) {
            $logbook->addLine('No changes pending for input data.');
        } else if ($event instanceof DataSheetTransactionEventInterface) {
            $logbook->addLine('Found ' . count($pendingChanges) . ' pending changes.');
            $this->applyChanges($event, $siblingCache, $pendingChanges, $logbook);
        } else {
            throw new BehaviorRuntimeError($this, 'Event ' . $event->getAlias() . ' not supported!', null, null, $logbook);
        }
        
        $this->finishWork($event, $logbook);
    }

    /**
     *
     * @param DataSheetInterface        $eventSheet
     * @param LazyHierarchicalDataCache $siblingCache
     * @param array                     $pendingChanges
     * @param BehaviorLogBook           $logBook
     * @return array
     */
    private function shiftIndices(
        DataSheetInterface          $eventSheet,
        LazyHierarchicalDataCache   $siblingCache,
        array                       $pendingChanges,
        BehaviorLogBook             $logBook) : array
    {
        // Prepare variables.
        $indexAlias = $this->getOrderNumberAttributeAlias();
        $uidAlias = $eventSheet->getUidColumnName();
        $startIndex = $this->getOrderStartsWith();
        $shiftedIndices = [];

        $logBook->addLine('Shifting indices of input data.');
        foreach ($pendingChanges as $pendingRow) {
            $siblingData = $siblingCache->getData($this->getParentsForRow($pendingRow));
            $pendingIndex = $pendingRow[$indexAlias];

            // If the pending index conflicts with the original data, we need to shift it.
            $emptyIndex = $pendingIndex === null || $pendingIndex === '';
            if($emptyIndex || in_array($pendingIndex, $siblingData['loaded']->getColumnValues($indexAlias))) {
                $safeIndex = $siblingData['maxIndex'] - $startIndex + 1;
                $newIndex = $pendingIndex + $safeIndex;
                $pendingRow[$indexAlias] = $newIndex;
                $shiftedIndices[$newIndex] = $pendingIndex;
            }

            $eventSheet->removeRowsByUid($pendingRow[$uidAlias]);
            $eventSheet->addRow($pendingRow);
        }

        return $shiftedIndices;
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
     * @param DataSheetEventInterface   $event
     * @param DataSheetInterface        $loadedData
     * @param LazyHierarchicalDataCache $cache
     * @param array                     $pendingChanges
     * Any necessary data updates will be appended to this array, since updating is deferred to `On...DataEvent`.
     * @param BehaviorLogBook           $logbook
     * @return void
     */
    private function groupAndOrderDataByParents(
        DataSheetEventInterface     $event,
        DataSheetInterface          $loadedData,
        LazyHierarchicalDataCache   $cache,
        array                       &$pendingChanges,
        BehaviorLogBook             $logbook): void
    {
        $logbook->addLine('Ordering event data...');
        $logbook->addIndent(1);
        
        // Prepare variables.
        $eventSheet = $event->getDataSheet();
        $uidAlias = $eventSheet->getUidColumnName();
        $onDelete = $event instanceof OnBeforeDeleteDataEvent;

        // Iterate over rows in the event sheet and update their ordering.
        foreach ($eventSheet->getRows() as $rowIndex => $row) {
            $logbook->addLine('Searching for siblings of row '. $rowIndex . ':');
            $logbook->addIndent(1);

            // Check if we already loaded sibling data for this UID before.
            $rowParents = $this->getParentsForRow($row);
            if ($cache->getData($rowParents)) {
                $logbook->addLine('Data for this group has already been processed, moving on.');
                $logbook->addIndent(-1);
                continue;
            }
            
            // Extract sibling data.
            $loadedSiblingsSheet = $this->extractLoadedSiblings($loadedData, $rowParents);
            $changedSiblingsSheet = $this->extractChangedSiblings($loadedSiblingsSheet, $eventSheet, $rowParents);
            $allSiblingsSheet = $loadedSiblingsSheet->merge($changedSiblingsSheet);
            
            // Add new nodes to the cache.
            foreach ($allSiblingsSheet->getRows() as $siblingRow) {
                $cache->addElement($this->getParentsForRow($siblingRow), $rowParents);
            }

            // On delete, all changed rows will be removed from the database,
            // so we have to remove them from our ordering data as well.
            if($onDelete) {
                foreach ($changedSiblingsSheet->getRows() as $changedRow) {
                    $allSiblingsSheet->removeRowsByUid($changedRow[$uidAlias]);
                }
            }

            $groupId = json_encode($rowParents);
            $logbook->addLine('Found ' . ($allSiblingsSheet->countRows()) . ' elements in group ' . $groupId . '.');
            $logbook->addDataSheet('Group-' . $groupId, $allSiblingsSheet);
            
            // Find and record changes.
            //$this->findPendingChanges($siblingData, $pendingChanges, $logbook);

            // Mark all siblings as PROCESSED, to avoid ordering the same group multiple times.
            try {
                $siblingData['flag'] = self::FLAG_PROCESSED;
                $cache->setData($siblingData['parents'], $siblingData);
            } catch (InvalidArgumentException $exception) {
                throw new BehaviorRuntimeError($this, 'Could not mark data as "' .  self::FLAG_PROCESSED .'": ' . $exception->getMessage(), null, $exception, $logbook);
            }
        }

        $logbook->addIndent(-1);
    }
    
    private function determineMissingIndices(
        DataSheetInterface $allSiblingData,
        BehaviorLogBook           $logbook) : array
    {
        // Determine where to insert new elements.
        $insertOnTop = ! $this->getAppendToEnd();
        $indexingAlias = $this->getOrderNumberAttributeAlias();
        
        if ($insertOnTop) {
            $insertionIndex = $this->getOrderStartsWith();
            $logbook->addLine('New elements will be inserted at the start with index ' . $this->getOrderStartsWith());
        } else {
            $maxIndex = max($allSiblingData->getColumnValues($indexingAlias));
            if ($maxIndex === "") {
                $insertionIndex = $this->getOrderStartsWith();
                $logbook->addLine('Could not determine insertion index, new elements will be inserted at index ' . $insertionIndex . ' by default.');
            } else {
                $insertionIndex = $maxIndex + 1;
                $logbook->addLine('New elements will be inserted at the end with index ' . $insertionIndex);
            }
        }

        // Sort sheet by ordering index.
        $sorters = new DataSorterList($allSiblingData->getWorkbench(), $allSiblingData);
        $sorters->addFromString($indexingAlias);
        $allSiblingData->sort($sorters, false);
        

        return $siblingData;
    }
    
    protected function extractLoadedSiblings(DataSheetInterface $loadedData, array $parents) : DataSheetInterface
    {
        $loadedSiblingsSheet = $this->createEmptyCopy($loadedData, true, false);
        
        foreach ($loadedData->getRows() as $loadedRow) {
            if ($this->belongsToGroup($loadedRow, $parents)) {
                $loadedSiblingsSheet->addRow($loadedRow);
            }
        }
        
        return $loadedSiblingsSheet;
    }
    
    protected function extractChangedSiblings(DataSheetInterface $loadedSiblingSheet, DataSheetInterface $eventData, array $parents) : DataSheetInterface
    {
        $uidAlias = $loadedSiblingSheet->getUidColumnName();
        $indexingAlias = $this->getOrderNumberAttributeAlias();
        $changedSiblingsSheet = $this->createEmptyCopy($eventData, true, false);
        
        foreach ($eventData->getRows() as $changedRow) {
            if (!$this->belongsToGroup($changedRow, $parents)) {
                continue;
            }

            $loadedRow = $loadedSiblingSheet->getRowByColumnValue($uidAlias, $changedRow[$uidAlias]);
            
            if ($loadedRow === null || $loadedRow[$indexingAlias] !== $changedRow[$indexingAlias]) {
                $changedSiblingsSheet->addRow($changedRow, true, false);
            }
        }


        return $changedSiblingsSheet;
    }

    private function buildSiblingCacheEntry(
        ?DataSheetInterface $loadedSiblingsSheet,
        ?DataSheetInterface $changedSiblingsSheet,
        ?DataSheetInterface $allSiblingsSheet,
        array               $parents,
        ?string             $flag) : array
    {
        $indexAlias = $this->getOrderNumberAttributeAlias();
        return [
            'loaded' => $loadedSiblingsSheet,
            'all' => $allSiblingsSheet,
            'changed' => $changedSiblingsSheet,
            'parents' => $parents,
            'flag' => $flag,
            'maxIndex' => max($allSiblingsSheet->getColumnValues($indexAlias))
        ];
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
        $siblingSheet = $siblingData['all'];
        $priorityUIds = $siblingData['changed']->getUidColumn()->getValues();
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
                $pendingChanges[] = $row;

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
            
            // Check if current row had priority.
            $isPriorityRow = in_array($row[$uidAlias], $priorityUIds);

            // Check indexingCache.
            if(!$isPriorityRow && key_exists($index, $indexingCache)) {
                $nextIndex = $indexingCache[$index];
                unset($indexingCache[$index]);
            }
            
            $regularUpdate = $index < $nextIndex && !$isPriorityRow;
            $closeGapUpdate = $index > $nextIndex && $closeGaps;
            if($regularUpdate || $closeGapUpdate) {
                $row[$indexAlias] = $nextIndex;
                $pendingChanges[] = $row;
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
     *
     * @param DataSheetTransactionEventInterface $event
     * @param LazyHierarchicalDataCache          $siblingCache
     * @param array                              $pendingChanges
     * @param BehaviorLogBook                    $logBook
     * @return void
     */
    private function applyChanges(
        DataSheetTransactionEventInterface $event,
        LazyHierarchicalDataCache          $siblingCache,
        array                              $pendingChanges,
        BehaviorLogBook                    $logBook): void
    {
        // Generate update sheet.
        $eventSheet = $event->getDataSheet();
        $updateSheet = $this->createEmptyCopy($eventSheet, true, false);
        foreach ($pendingChanges as $pending) {
            $updateSheet->addRow($pending);
        }
        
        if(!$event instanceof OnDeleteDataEvent) {
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
        $startIndex = $this->getOrderStartsWith();

        foreach ($shiftSheet->getRows() as $rowNr => $row) {
            $siblingData = $siblingCache->getData($this->getParentsForRow($row));
            $pendingIndex = $row[$indexAlias];
            $safeIndex = $siblingData['maxIndex'] - $startIndex + 1;
            
            $shiftSheet->setCellValue($indexAlias, $rowNr, $pendingIndex + $safeIndex);
        }

        try {
            $transaction = $event->getTransaction();
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
     * Extracts an array with ALL parents of a row, including duplicates.
     *
     * @param array $row
     * @return array
     */
    protected function getParentsForRow(array $row) : array
    {
        $parents = [];
        $parentAliases = $this->getParentAliases();

        foreach ($parentAliases as $parentAlias) {
            // Get parent information.
            $parent = $row[$parentAlias];
            $parents[] = $parent;
        }

        return $parents;
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
     * @param DataSheetInterface $dataSheet
     * @param array              $parents
     * @return void
     * @see OrderingBehavior::belongsToGroup()
     *
     */
    // TODO geb 2024-10-11: This function is meant as an optimization for large data sets. The idea is to reduce the
    // TODO amount of data retrieved by the SQL-Query. This might not be a good idea, since this filter scheme has O(n²) with
    // TODO respect to the number of parent aliases. Alternatively we might do a simple filter for just one parent or none at all.
    // TODO Any filtering not done via SQL must be done in code, which is easier to do, but may incur long load times for large data sets.
    private function addFiltersFromParentAliases(DataSheetInterface $dataSheet, array $parents): void
    {
        // Prepare variables.
        $metaObject = $dataSheet->getMetaObject();
        $parentAliases = $this->getParentAliases();
        $conditionGroup = ConditionGroupFactory::createAND($metaObject);
        $processedParents = [];

        foreach ($parents as $parent) {
            // Since we concatenate our filters with OR, we only need to filter for
            // each specific parent once.
            if(in_array($parent, $processedParents)) {
                continue;
            }
            $processedParents[] = $parent;

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
     * @param int $value
     * @return OrderingBehavior
     *@deprecated use setOrderStartsWith() / order_starts_with instead
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
     * Set FALSE to leave gaps in the order number if an element is deleted from the middle
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
     * @param bool $trueOrFalse
     * @return OrderingBehavior
     *@deprecated use setAppendToEnd() / new_element_on_top instead
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
     * @param UxonObject $value
     * @return OrderingBehavior
     *@deprecated use setOrderWithinAttributes() / order_within_attributes instead
     *
     */
    protected function setIndexingBoundaryAttributes(UxonObject $value) : OrderingBehavior
    {
        return $this->setOrderWithinAttributes($value);
    }

    /**
     * @param UxonObject $value
     * @return OrderingBehavior
     *@deprecated use setParentAliases() / parent_aliases instead
     *
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
     * @param string $alias
     * @return OrderingBehavior
     *@deprecated  use setOrderAttribute() / order_number_attribute instead
     *
     */
    protected function setOrderIndexAttribute(string $alias) : OrderingBehavior
    {
        return $this->setOrderNumberAttribute($alias);
    }
}