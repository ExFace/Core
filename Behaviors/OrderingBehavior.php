<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Utils\LazyHierarchicalDataCache;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * This behavior orders given indizes within a boundary
 *
 * The event is triggered before every create, update or delete operation.
 * It works by searching data in the data source for matches in every element of `indexing_boundary_attributes`
 * - multiple for complex tree structures - to find all neighboring elements.
 * These then will be ordered by the `order_index_attribute` - depending on the properties `close_gaps`
 * the indices will be incremented evenly, correcting all missing values. Duplicate values will always be corrected.
 *
 * By default close_gaps will occure.
 *
 * In addition you can change the `starting_index`, deciding which index should be the first to start
 * with and depending on `new_element_ontop` this will be relevant with every new entry.
 *
 * By default `new_element_ontop` will be set to false, preventing reordering all related elements within the same boundary.
 * CAUTION: Only elements with UIDs can make use of this configuration!
 *
 * ## Examples
 *
 * ### Order pages by it's position within their menu parent. Closing gaps and inserting new elements otop.
 *
 * Example config to order the menu postions of `exface.Core.PAGE`.
 *
 * ```
 *  {
 *      "indexing_boundary_attributes": [
 *          "MENU_PARENT"
 *      ],
 *      "close_gaps": true,
 *      "order_index_attribute": "MENU_POSITION",
 *      "new_element_ontop": false,
 *      "starting_index": 1
 *  }
 * 
 * ```
 *
 * @author Miriam Seitz
 *        
 */
class OrderingBehavior extends AbstractBehavior
{
    // internal variables
    private bool $working = false;

    // configurable variables
    private int $startIndex = 1;
    private bool $closeGaps = true;
    private bool $insertNewOnTop = false;
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
    public function handleEvent(DataSheetEventInterface $event) : void
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

        // Get datasheet and make sure it has a UID column.
        $eventSheet = $event->getDataSheet();
        if ($eventSheet->hasUidColumn() === false) {
            $logbook->addLine('Cannot order objects with no Uid attribute.');
            return;
        }

        // Begin work.
        $logbook->addLine('Received ' . $eventSheet->countRows() . ' rows of ' . $eventSheet->getMetaObject()->__toString());
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        $this->working = true;

        // Order data.
        $pendingChanges = $this->groupDataByParent($eventSheet, $logbook);

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
     * Groups all rows in the event sheet by their parents and
     * applies an ascending order within the groups according to their index alias.
     *
     * @param DataSheetInterface $eventSheet
     * @param LogBookInterface $logbook
     * @return array
     * An array containing all changes necessary to achieve the desired ordering.
     */
    private function groupDataByParent(
    	DataSheetInterface $eventSheet,
    	LogBookInterface  $logbook): array
    {
        // Prepare variables.
        $cache = new LazyHierarchicalDataCache();
        $pendingChanges = [];
        $indexAlias = $this->getIndexAlias();
        $uidAlias = $eventSheet->getUidColumnName();

        // Iterate over changed rows and update the ordering.
        foreach ($eventSheet->getRows() as $changedRow) {
            // Find, load and cache the row and its siblings.
            $siblingSheet = $this->getSiblings($eventSheet, $changedRow, $indexAlias, $cache, $logbook);

            // If we already ordered the group this row belongs to, continue.
            if($siblingSheet === "PROCESSED") {
                continue;
            }

            // Find and record changes.
            try {
                $pendingChanges = array_merge($pendingChanges, $this->findPendingChanges($siblingSheet));
            } catch (BehaviorConfigurationError $e) {
                throw new BehaviorConfigurationError($this, $e->getMessage(), $logbook, $e);
            }

            // Mark all siblings as DONE, to avoid ordering the same group multiple times.
            $cache->setData($changedRow[$uidAlias], "PROCESSED");
        }

        unset($cache);
        return $pendingChanges;
    }

    /**
     * Finds all siblings of a given row. A sibling is any row that has the EXACT same parentage, including EMPTY values.
     *
     * Loads, processes and caches all relevant data for this row and its siblings.
     *
     * Multiple calls to this function with either the same row or one of its siblings will return the cached data without
     * performing any additional work.
     *
     * @param DataSheetInterface $eventSheet
     * @param array $row
     * @param string $indexingAlias
     * @param LazyHierarchicalDataCache $cache
     * @param LogBookInterface $logbook
     * @return DataSheetInterface|string
     */
    private function getSiblings(
        DataSheetInterface        $eventSheet,
        array                     $row,
        string                    $indexingAlias,
        LazyHierarchicalDataCache $cache,
        LogBookInterface          $logbook): DataSheetInterface | string
    {
        // Check if we already loaded the siblings for this UID before.
        $uidAlias = $eventSheet->getUidColumnName();
        if($indexSheet = $cache->getData($row[$uidAlias])) {
            return $indexSheet;
        }

        // Prepare variables.
        $parents = [];
        $parentAliases = $this->getParentAliases();
        $indexSheet = $this->createEmptyCopy($eventSheet);

        // Add filters for all parent aliases.
        foreach ($parentAliases as $parentAlias) {
            // Get parent information.
            $parent = $row[$parentAlias];
            // If this information is new, add it to the collection.
            if(!in_array($parent, $parents)){
                $parents[] = $parent;
            }
            // Add column with parent alias.
            $indexSheet->getColumns()->addFromExpression($parentAlias);
            // Add filter.
            if($parent === null) {
                // If the parent information is null, we need to add a special filter.
                $indexSheet->getFilters()->addConditionForAttributeIsNull($parentAlias);
            } else {
                // Otherwise we add a regular filter.
                $indexSheet->getFilters()->addConditionFromString(
                    $parentAlias,
                    $parent,
                    ComparatorDataType::EQUALS,
                    false);
            }
        }

        // Load old data.
        $indexSheet->dataRead();
        // Extract new data and merge it with the old.
        $newData = $eventSheet->extract($indexSheet->getFilters());
        $indexSheet->addRows($newData->getRows(), true);

        // Calculate insertion index.
        $insertionIndex = $this->getInsertNewOnTop() ?
            $this->getStartIndex() :
            max($indexSheet->getColumnValues($indexingAlias));

        // Post-Process rows.
        foreach ($indexSheet->getRows() as $rowNumber => $indexedRow) {
            // Update index with value from event, if the event has a corresponding row.
            $correspondingRow = $eventSheet->getRowByColumnValue($uidAlias, $indexedRow[$uidAlias]);
            $index = $correspondingRow ? $correspondingRow[$indexingAlias] : $indexedRow[$indexingAlias];
            $index = $index !== null && $index !== "" ? $index : $insertionIndex;
            $indexSheet->setCellValue($indexingAlias, $rowNumber, $index);

            // Add row to cache.
            $cache->addElement($indexedRow[$uidAlias], $parents);
        }

        // Save data to cache.
        $indexSheet->getSorters()->addFromString($indexingAlias, SortingDirectionsDataType::ASC);
        $indexSheet->sort(null, false);
        $cache->setData($row[$uidAlias], $indexSheet);

        $logbook->addLine(
            'Found '
            . $indexSheet->countRows()
            . ' neighboring elements for the ' . $row[$uidAlias] . ' event data object.');

        return $indexSheet;
    }

    /**
     * Finds all indices that need to be changed to successfully update the ordering.
     *
     * @param DataSheetInterface $indexSheet
     * The data sheet to be updated. Indices must be in ASCENDING order!
     */
    private function findPendingChanges(DataSheetInterface $indexSheet): array
    {
        // Prepare variables.
        $nextIndex = $this->getStartIndex();
        $indexAlias = $this->getIndexAlias();
        $closeGaps = $this->getCloseGaps();
        $pendingChanges = [];

        // Iterate over all rows of the provided data sheet to find and record all the changes we have
        // to make to satisfy our ordering conditions.
        foreach ($indexSheet->getRows() as $row) {
            // Get current index of row.
            $index = $row[$indexAlias];
            if(!is_numeric($index)) {
                throw new BehaviorConfigurationError($this, 'Cannot order values of attribute "' . $indexAlias . '": invalid value "' . $index . "' encountered! Ordering indices must be numeric.");
            }

            // Next index always points to the next open slot. If index is smaller or equal to that, we have to update.
            // Otherwise, we only have to update if gap closing was enabled.
            if($index <= $nextIndex || $closeGaps) {
                if($index != $nextIndex) {
                    // Mark row as pending.
                    $row[$indexAlias] = $index = $nextIndex;
                    $pendingChanges[] = $row;
                }
            }

            // Update next index.
            $nextIndex = $index + 1;
        }

        return $pendingChanges;
    }

    /**
     *
     * @param DataSheetInterface $sheet
     * @return DataSheetInterface
     */
    private function createEmptyCopy(DataSheetInterface $sheet): DataSheetInterface
    {
        $emptyCopy = $sheet->copy();
        $emptyCopy->removeRows();

        return $emptyCopy;
    }

    /**
     *
     * @param DataSheetInterface $eventSheet
     * @param array $pendingChanges
     * @return void
     */
    private function applyChanges(
    	DataSheetInterface      $eventSheet,
    	array                   $pendingChanges): void
    {
        $uidAlias = $eventSheet->getUidColumnName();
        $indexAlias = $this->getIndexAlias();

        foreach ($pendingChanges as $pending) {
            if($rowToChange = $eventSheet->getUidColumn()->findRowByValue($pending[$uidAlias])) {
                $eventSheet->setCellValue($indexAlias, $rowToChange, $pending[$indexAlias]);
            } else {
                $eventSheet->addRow($pending);
            }
        }
    }

    /**
     * Define from where the behavior starts counting.
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
}