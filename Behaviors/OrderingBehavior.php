<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataSorterList;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Utils\LazyHierarchicalDataCache;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Factories\ConditionGroupFactory;
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
 * This behavior orders given indices within the hierarchy.
 * 
 * The event is triggered before every create, update or delete operation.
 * It establishes a hierarchy based on the `parent_aliases` you provide and then orders
 * all elements among their siblings. Siblings are elements that share exactly the same parents.
 * 
 * - `index_alias` (REQUIRED): The resulting indices will be written to this column. 
 * - `close_gaps`: If TRUE (default), gaps between indices will be closed.
 * - `starting_index`: Ordering will start with this index.
 * - `new_element_on_top`: If TRUE (default), any elements that have no ordering index specified, will be
 * ordered first among their siblings.
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
 *      "parent_aliases": [
 *          "MENU_PARENT"
 *      ],
 *      "close_gaps": true,
 *      "index_alias": "MENU_POSITION",
 *      "new_element_on_top": true,
 *      "starting_index": 1
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
    private bool $insertNewOnTop = true;
    private mixed $indexAlias = null;
    private mixed $parentAliases = [];

    /**
     *
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners(): BehaviorInterface
    {
        $handler = array($this, 'handleEvent');
        $priority = $this->getPriority();
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), $handler, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), $handler, $priority);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), $handler, $priority);

        return $this;
    }

    /**
     *
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners(): BehaviorInterface
    {
        $handler = array($this, 'handleEvent');

        $this->getWorkbench()->eventManager()->removeListener(OnBeforeCreateDataEvent::getEventName(), $handler);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), $handler);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), $handler);

        return $this;
    }

    /**
     *
     * @param DataSheetEventInterface $event
     */
    public function handleEvent(DataSheetEventInterface $event): void
    {
        // Ignore MetaObjects we are not associated with.
        if (!$event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

        // Create logbook.
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logbook->setIndentActive(1);

        // Prevent recursion.
        if ($this->working === true) {
            $logbook->addLine('Ordering not necessary. Element is already in the process of being ordered.');
            return;
        }

        // Get datasheet.
        $eventSheet = $event->getDataSheet();
        // Make sure it has a UID column.
        if ($eventSheet->hasUidColumn() === false) {
            $logbook->addLine('Cannot order objects with no Uid attribute.');
            return;
        }
        // Fetch any missing columns.
        $this->fetchMissingColumns($eventSheet);

        // Begin work.
        $logbook->addLine('Received ' . $eventSheet->countRows() . ' rows of ' . $eventSheet->getMetaObject()->__toString());
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        $this->working = true;

        // Order data.
        $pendingChanges = [];
        $this->groupDataByParent($eventSheet, $pendingChanges, $logbook);

        // Apply changes, if any.
        if (count($pendingChanges) === 0) {
            $logbook->addLine('No changes to order necessary.');
        } else {
            $this->applyChanges($eventSheet, $pendingChanges);
        }

        // Finish work.
        $this->working = false;
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    /**
     * Loads any missing parent columns and merges their data into the event sheet.
     *
     * @param DataSheetInterface $eventSheet
     * @return void
     */
    function fetchMissingColumns(DataSheetInterface $eventSheet): void
    {
        // Load missing parent columns.
        $fetchSheet = $this->createEmptyCopy($eventSheet, true, true);
        $sampleRow = $eventSheet->getRow();
        foreach ($this->getParentAliases() as $parentAlias) {
            // If the row is not present in the event sheet, we need to fetch it.
            if (!key_exists($parentAlias, $sampleRow)) {
                $fetchSheet->getColumns()->addFromExpression($parentAlias);
            }
        }
        // Load missing column data and merge it into the event sheet.
        if ($fetchSheet->getColumns()->count() > 0) {
            $fetchSheet->getFilters()->addConditionFromColumnValues($eventSheet->getUidColumn(), true);
            $fetchSheet->dataRead();
            $eventSheet->joinLeft($fetchSheet, $eventSheet->getUidColumnName(), $fetchSheet->getUidColumnName());
        }
    }

    /**
     * Groups all rows in the event sheet by their parents and
     * applies an ascending order within the groups according to their index alias.
     *
     * @param DataSheetInterface $eventSheet
     * @param array              $pendingChanges
     * An array to which any changes necessary to achieve the desired ordering will be appended.
     * @param LogBookInterface   $logbook
     * @return void
     */
    private function groupDataByParent(
        DataSheetInterface $eventSheet,
        array              &$pendingChanges,
        LogBookInterface   $logbook): void
    {
        // Prepare variables.
        $cache = new LazyHierarchicalDataCache();
        $indexAlias = $this->getIndexAlias();
        $uidAlias = $eventSheet->getUidColumnName();

        // Iterate over changed rows and update the ordering.
        foreach ($eventSheet->getRows() as $changedRow) {
            // Find, load and cache the row and its siblings.
            $siblingSheet = $this->getSiblings($eventSheet, $pendingChanges, $changedRow, $indexAlias, $cache, $logbook);

            // If we already ordered the group this row belongs to, continue.
            if ($siblingSheet === "PROCESSED") {
                continue;
            }

            // Find and record changes.
            try {
                $this->findPendingChanges($siblingSheet, $pendingChanges);
            } catch (BehaviorConfigurationError $e) {
                throw new BehaviorConfigurationError($this, $e->getMessage(), $logbook, $e);
            }

            // Mark all siblings as DONE, to avoid ordering the same group multiple times.
            $cache->setData($changedRow[$uidAlias], "PROCESSED");
        }

        unset($cache);
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
     * @param DataSheetInterface        $eventSheet
     * @param array                     $pendingChanges
     * An array to which any changes necessary to achieve the desired ordering will be appended.
     * @param array                     $row
     * @param string                    $indexingAlias
     * @param LazyHierarchicalDataCache $cache
     * @param LogBookInterface          $logbook
     * @return DataSheetInterface|string
     */
    private function getSiblings(
        DataSheetInterface        $eventSheet,
        array                     &$pendingChanges,
        array                     $row,
        string                    $indexingAlias,
        LazyHierarchicalDataCache $cache,
        LogBookInterface          $logbook): DataSheetInterface|string
    {
        // Check if we already loaded the siblings for this UID before.
        $uidAlias = $eventSheet->getUidColumnName();
        if ($siblingSheet = $cache->getData($row[$uidAlias])) {
            return $siblingSheet;
        }

        // Prepare variables.
        $siblingSheet = $this->createEmptyCopy($eventSheet, true, false);
        $parents = $this->addFiltersFromParentAliases($siblingSheet, $row);
        // Load old data from database.
        $siblingSheet->dataRead();
        // Extract new data and merge it with the old.
        $newData = $eventSheet->extract($siblingSheet->getFilters());
        $siblingSheet->addRows($newData->getRows(), true);

        // Determine where to insert new elements.
        $insertOnTop = $this->getInsertNewOnTop();
        if ($insertOnTop) {
            $insertionIndex = $this->getStartIndex() - 1;
        } else {
            // If we have to insert at the bottom of our hierarchy, we have to
            // filter out any rows that are not in our group.
            foreach ($siblingSheet->getRows() as $siblingRow) {
                if (!$this->belongsToGroup($siblingRow, $parents)) {
                    $siblingSheet->removeRowsByUid($siblingRow[$uidAlias]);
                }
            }

            $insertionIndex = max($siblingSheet->getColumnValues($indexingAlias));
            if ($insertionIndex !== "") {
                $insertionIndex++;
            } else {
                $insertionIndex = $this->getStartIndex();
            }
        }

        // Post-Process rows.
        foreach ($siblingSheet->getRows() as $rowNumber => $siblingRow) {
            // If we didn't do this earlier, we now have to remove all rows, that are not part of our sibling group.
            if ($insertOnTop && !$this->belongsToGroup($siblingRow, $parents)) {
                $siblingSheet->removeRowsByUid($siblingRow[$uidAlias]);
                continue;
            }

            // Fill missing indices.
            $index = $siblingRow[$indexingAlias];
            if ($index === null || $index === "") {
                // Update sheet.
                $siblingSheet->setCellValue($indexingAlias, $rowNumber, $insertionIndex);
                // Record change.
                $siblingRow[$indexingAlias] = $insertionIndex;
                $pendingChanges[$siblingRow[$uidAlias]] = $siblingRow;
            }

            // Add row to cache.
            $cache->addElement($siblingRow[$uidAlias], $parents);
        }

        // Save data to cache.
        $sorters = new DataSorterList($siblingSheet->getWorkbench(), $siblingSheet);
        $sorters->addFromString($indexingAlias);
        $siblingSheet->sort($sorters, false);
        $cache->setData($row[$uidAlias], $siblingSheet);

        $logbook->addLine(
            'Found '
            . $siblingSheet->countRows()
            . ' neighboring elements for the ' . $row[$uidAlias] . ' event data object.');

        return $siblingSheet;
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
     * @param DataSheetInterface $indexSheet
     * The data sheet to be updated. Indices must be in ASCENDING order!
     * @param array              $pendingChanges
     * An array to which any changes necessary to achieve the desired ordering will be appended.
     */
    private function findPendingChanges(DataSheetInterface $indexSheet, array &$pendingChanges): void
    {
        // Prepare variables.
        $nextIndex = $this->getStartIndex();
        $indexAlias = $this->getIndexAlias();
        $uidAlias = $indexSheet->getUidColumnName();
        $closeGaps = $this->getCloseGaps();

        // Iterate over all rows of the provided data sheet to find and record all the changes we have
        // to make to satisfy our ordering conditions.
        foreach ($indexSheet->getRows() as $row) {
            // Get current index of row.
            $index = $row[$indexAlias];
            if (!is_numeric($index)) {
                throw new BehaviorConfigurationError($this, 'Cannot order values of attribute "' . $indexAlias . '": invalid value "' . $index . "' encountered! Ordering indices must be numeric.");
            }

            // Next index always points to the next open slot. If index is smaller or equal to that, we have to update.
            // Otherwise, we only have to update if gap closing was enabled.
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
     * @return void
     */
    private function applyChanges(
        DataSheetInterface $eventSheet,
        array              $pendingChanges): void
    {
        $uidAlias = $eventSheet->getUidColumnName();
        $indexAlias = $this->getIndexAlias();
        $updateSheet = $this->createEmptyCopy($eventSheet, true, false);

        foreach ($pendingChanges as $pending) {
            if (($rowToChange = $eventSheet->getUidColumn()->findRowByValue($pending[$uidAlias])) !== false) {
                $eventSheet->setCellValue($indexAlias, $rowToChange, $pending[$indexAlias]);
            } else {
                $updateSheet->addRow($pending);
            }
        }

        $updateSheet->dataSave();
    }

    /**
     * Define where the behavior starts counting.
     * 
     * A starting index of `1` for example represents an intuitive count from
     * `1` to `infinity`.
     * 
     * @uxon-property starting_index
     * @uxon-type integer
     * @uxon-default 0
     * 
     * @param int $value
     * @return OrderingBehavior
     */
    protected function setStartingIndex(int $value): OrderingBehavior
    {
        $this->startIndex = $value;
        return $this;
    }

    /**
     *
     * @return int
     */
    protected function getStartIndex(): int
    {
        return $this->startIndex;
    }

    /**
     * Toggle, whether the behavior should close gaps between indices.
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
     * @uxon-property new_element_on_top
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return OrderingBehavior
     */
    protected function setNewElementOnTop(bool $trueOrFalse): OrderingBehavior
    {
        $this->insertNewOnTop = $trueOrFalse;
        return $this;
    }

    /**
     *
     * @return bool
     */
    protected function getInsertNewOnTop(): bool
    {
        return $this->insertNewOnTop;
    }

    /**
     *
     * @return array
     */
    protected function getParentAliases(): array
    {
        return $this->parentAliases;
    }

    /**
     * Define from which columns the behavior should determine the parents of a row.
     * 
     * @uxon-property parent_aliases
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * @uxon-required true
     * 
     * @param UxonObject $value
     * @return OrderingBehavior
     */
    protected function setParentAliases(UxonObject $value): OrderingBehavior
    {
        $this->parentAliases = $value->toArray();
        return $this;
    }

    /**
     * @deprecated use parent_aliases instead
     * 
     * @param \exface\Core\CommonLogic\UxonObject $value
     * @return \exface\Core\Behaviors\OrderingBehavior
     */
    protected function setIndexingBoundaryAttributes(UxonObject $value) : OrderingBehavior
    {
        return $this->setParentAliases($value);
    }

    /**
     *
     * @return string
     */
    protected function getIndexAlias(): string
    {
        return $this->indexAlias;
    }

    /**
     * Define which attribute will store the calculated indices. Must be compatible with integers.
     * 
     * @uxon-property index_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return OrderingBehavior
     */
    protected function setIndexAlias(string $value): OrderingBehavior
    {
        $this->indexAlias = $value;
        return $this;
    }
#
    /**
     * @deprecated  use index_alias instead
     * @param string $alias
     * @return \exface\Core\Behaviors\OrderingBehavior
     */
    protected function setOrderIndexAttribute(string $alias) : OrderingBehavior
    {
        return $this->setIndexAlias($alias);
    }
}