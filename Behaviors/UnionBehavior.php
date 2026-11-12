<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Model\Behaviors\UnionMapping;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use WeakMap;

/**
 * Appends rows from other objects, like SQL UNION ALL, across any readable data sources.
 * 
 * The UNION operates on a single **base object** and adds rows from one or more
 * **union objects** on read operations for the base object. Duplicates are preserved.
 * A separate native read of the base object contributes its own rows. A base object without a
 * readable source can instead act as a virtual result object and contributes no
 * rows of its own. Currently only read operations are supported!
 * 
 * Use `union_objects` to list source objects. Objects extending the base object
 * use matching attribute aliases automatically, including relation paths, and
 * need no explicit mappings:
 * 
 * ```json
 * {
 *     "union_objects": [
 *         {"object_alias": "my.App.SEARCH_RESULT"}
 *     ]
 * }
 * 
 * ```
 * 
 * ## Examples
 * 
 * ### Unrelated objects
 * 
 * For unrelated objects, explicitly map source columns to result attributes:
 * 
 * ```json
 * {
 *     "union_objects": [{
 *         "object_alias": "my.App.ORDER_REQUEST",
 *         "union_to_base_mapper": {
 *             "column_to_column_mappings": [
 *                 {"from": "ID", "to": "UID"},
 *                 {"from": "TITLE", "to": "NAME"},
 *                 {"from": "'Order request'", "to": "SOURCE"}
 *             ]
 *         }
 *     }]
 * }
 * 
 * ```
 * 
 * To display a row's origin, map a constant to a base attribute such as `SOURCE`
 * in each source's mapping, as shown above.
 * 
 * ### Custom filter and sorter mappings
 * 
 * Attribute-to-attribute mappings automatically translate filters and sorters.
 * Nested filter groups retain their AND/OR structure. For special rules, add a
 * `base_to_union_mapper` to the same source entry:
 * 
 * ```json
 * {
 *     "base_to_union_mapper": {
 *         "filter_to_filter_mappings": [
 *             {"from": "NAME", "to": "TITLE", "from_comparator": "==", "to_comparator": "="}
 *         ],
 *         "sorter_to_sorter_mappings": [
 *             {"from": "NAME", "to": "TITLE"}
 *         ]
 *     }
 * }
 * 
 * ```
 * 
 * Sorter mappings preserve the requested direction unless `to_direction` is set
 * to `ASC` or `DESC`. Explicit rules take precedence over derived ones. Mappings
 * for unrequested result columns are not executed or read. Missing mappings for
 * requested columns, filters or sorters are errors.
 * 
 * ## Sorting and pagination
 * 
 * All sources receive filters and sorters before reading. For a page of results,
 * each source reads from its beginning through the requested offset plus page
 * length, with one additional row to detect further results. After all union
 * behaviors have appended rows, the complete candidate set is sorted and paginated
 * once. This can read substantially more rows than the requested page.
 * 
 * ## Multiple behaviors and cascading unions
 * 
 * Several union behaviors can contribute to one object. Sources can themselves
 * use unions; cyclic configurations are rejected. A union behavior inherited from
 * a parent does not run on the child: otherwise a search-result source could union
 * itself back into its parent. Add a separate behavior to the child for intentional
 * cascading unions.
 *
 * The first participating behavior coordinates a replacement read: it prevents
 * the original query, reads native base rows once, appends every behavior's
 * contributions, and finalizes once. It then dispatches the completed result's
 * after-read event. The original before-read event keeps its requested pagination.
 * The private native sheet bypasses only UnionBehavior; authorization and other
 * behaviors remain active. This coordination is internal to UnionBehavior and
 * requires no union-specific knowledge in DataSheet or other components.
 * 
 * ## Limitations
 * 
 * - Each requested column needs exactly one source-to-base column mapping.
 * - Source-to-base mappers currently support `column_to_column_mappings` only.
 * - Related data requires union objects that extend the base object.
 * - Grouped/aggregated reads and column totals are not supported.
 * - Updates and deletes are rejected; writes are not routed to source objects.
 * - Designers must ensure UIDs are unique across all contributing rows.
 * - Selecting only constants requires a UID attribute on the source to retain
 *   the number of source rows.
 * - Unbounded reads collect every matching row in memory.
 * 
 * @author Andrej Kabachnik
 */
class UnionBehavior extends AbstractBehavior
{
    private ?UxonObject $unionObjectsUxon = null;

    /** @var UnionMapping[]|null */
    private ?array $unionObjects = null;

    private ?MetaObjectInterface $baseObject = null;

    /** @var WeakMap<OnBeforeReadDataEvent,UnionBehavior[]>|null */
    private static ?WeakMap $pendingReads = null;

    /** @var WeakMap<DataSheetInterface,bool>|null */
    private static ?WeakMap $nativeReadSheets = null;

    /**
     * Objects whose rows are appended, optionally with explicit column mappings.
     *
     * @uxon-property union_objects
     * @uxon-type \exface\Core\CommonLogic\Model\Behaviors\UnionMapping[]
     * @uxon-template [{"object_alias": ""}]
     * @uxon-required true
     * 
     * @param UxonObject $uxon
     * @return UnionBehavior
     */
    public function setUnionObjects(UxonObject $uxon) : UnionBehavior
    {
        $this->setUnionBaseObject($this->getObject());
        $this->unionObjectsUxon = $uxon->copy();
        $this->unionObjects = null;
        return $this;
    }

    /**
     * Return the original object on which this union behavior was configured.
     * 
     * Unlike getObject(), this reference is preserved when the behavior is copied
     * onto an inheriting object. Comparing the two keeps inherited copies inactive,
     * preventing a union source from recursively adding the parent's union sources.
     * Returns NULL until the union configuration has been assigned.
     * 
     * @return MetaObjectInterface|null
     */
    public function getUnionBaseObject() : ?MetaObjectInterface
    {
        return $this->baseObject;
    }

    /**
     * Record the original owner when assigning the union configuration.
     * 
     * This is internal state, not a UXON option. Inheritance changes getObject()
     * through setObject(), but must not change this reference: inherited copies
     * must retain the original owner so they can be excluded from event handling.
     * 
     * @param MetaObjectInterface $object
     * @return UnionBehavior
     */
    protected function setUnionBaseObject(MetaObjectInterface $object) : UnionBehavior
    {
        $this->baseObject = $object;
        return $this;
    }

    /**
     * Keep inherited copies from unioning the parent's sources into themselves.
     *
     * {@inheritDoc}
     * @see AbstractBehavior::copy()
     */
    public function copy() : self
    {
        $copy = parent::copy();
        $copy->unionObjectsUxon = $this->unionObjectsUxon?->copy();
        $copy->unionObjects = null;
        return $copy;
    }

    /**
     * Instantiate and cache union mappings on first access, not during configuration.
     * 
     * Requests that do not read this object therefore never instantiate its mappings.
     * Copies build their own mappings lazily so each mapping refers to its own behavior.
     * Calling setUnionObjects() invalidates the cache.
     * 
     * @return UnionMapping[]
     */
    public function getUnionMappings() : array
    {
        if ($this->unionObjects === null) {
            $mappings = [];
            foreach ($this->unionObjectsUxon ?? new UxonObject([]) as $entry) {
                $mappings[] = new UnionMapping($this, $entry);
            }
            $this->unionObjects = $mappings;
        }
        return $this->unionObjects;
    }

    /**
     * {@inheritDoc}
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $events = $this->getWorkbench()->eventManager();
        $events->addListener(OnBeforeReadDataEvent::getEventName(), [$this, 'onBeforeReadPrepare'], $this->getPriority());
        $events->addListener(OnBeforeReadDataEvent::getEventName(), [$this, 'onBeforeReadDoUnion'], EventManagerInterface::PRIORITY_MIN);
        $events->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onWriteThrowError'], $this->getPriority());
        $events->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onWriteThrowError'], $this->getPriority());
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $events = $this->getWorkbench()->eventManager();
        foreach ([
            OnBeforeReadDataEvent::class => ['onBeforeReadPrepare', 'onBeforeReadDoUnion'],
            OnBeforeUpdateDataEvent::class => ['onWriteThrowError'],
            OnBeforeDeleteDataEvent::class => ['onWriteThrowError']
        ] as $event => $methods) {
            foreach ($methods as $method) {
                $events->removeListener($event::getEventName(), [$this, $method]);
            }
        }
        return $this;
    }

    /**
     * Claim the replacement read and collect all matching union behaviors in priority order.
     *
     * The first participating behavior coordinates this event. Later participants
     * join the same request instead of reading the native base or finalizing again.
     * Prevent the original query immediately, but leave its pagination unchanged.
     *
     * @param OnBeforeReadDataEvent $event
     * @return void
     */
    public function onBeforeReadPrepare(OnBeforeReadDataEvent $event) : void
    {
        // Ignore disabled or inherited copies and reads of other objects.
        if (! $this->matches($event)) {
            return;
        }
        // A source read returning to this active union would recurse indefinitely.
        if ($this->isInProgress()) {
            throw new BehaviorRuntimeError($this, 'Cyclic union read detected for "' . $this->getObject()->getAliasWithNamespace() . '".');
        }
        // Respect reads prevented by another plugin; only join a read claimed by a union.
        if ($event->isPreventRead() && ! isset(self::$pendingReads[$event])) {
            return;
        }
        $sheet = $event->getDataSheet();
        // Appending rows cannot combine grouped results or totals correctly.
        if ($sheet->hasAggregations() || $sheet->hasAggregateAll() || $sheet->hasColumTotals()) {
            throw new BehaviorRuntimeError($this, 'UnionBehavior does not support aggregated sheets or totals.');
        }
        foreach ($sheet->getColumns() as $column) {
            // Column-level aggregates are unsupported even without sheet-level grouping.
            if ($column->hasAggregator()) {
                throw new BehaviorRuntimeError($this, 'UnionBehavior does not support aggregated columns.');
            }
        }
        // Use the base defaults for every source and the final in-memory sort.
        if ($sheet->getSorters()->isEmpty() && $sheet->getAutoSort()) {
            foreach ($this->getObject()->getDefaultSorters() as $sorter) {
                $sheet->getSorters()->addFromString($sorter->getAttributeAlias(), $sorter->getDirection());
            }
        }
        foreach ($sheet->getSorters() as $sorter) {
            // In-memory sorting needs the values, even when the column is not displayed.
            if (! $sheet->getColumns()->getByExpression($sorter->getAttributeAlias())) {
                $sheet->getColumns()->addFromExpression($sorter->getAttributeAlias(), null, true);
            }
        }
        self::$pendingReads = self::$pendingReads ?? new WeakMap();
        $unionBehaviors = self::$pendingReads[$event] ?? [];
        if (! in_array($this, $unionBehaviors, true)) {
            $unionBehaviors[] = $this;
        }
        self::$pendingReads[$event] = $unionBehaviors;
        $event->preventRead();
    }

    /**
     * Read and combine the base rows and all union sources instead of running the original query.
     *
     * One object can have several UnionBehavior instances. Each registers itself
     * for this read in onBeforeReadPrepare(). The first registered behavior does
     * all the work here; the others return without repeating it. PRIORITY_MIN
     * lets normal-priority listeners finish preparing the request first.
     *
     * For example, SEARCH_RESULT can have one union behavior adding orders and
     * another adding invoices. The first behavior reads SEARCH_RESULT's own rows
     * once (if it has a readable source), then asks both behaviors to append their
     * source rows. It sorts the combined rows and applies the requested page once.
     * A request for the first 10 rows therefore returns up to 10 rows overall,
     * not 10 rows from each source. Duplicates are kept.
     *
     * The base is read through a separate sheet marked to skip only UnionBehavior,
     * avoiding recursion while keeping authorization and other behaviors active.
     * Each source reads enough candidates for the requested page plus one extra
     * row to detect further results. The original request's pagination stays unchanged.
     * This method fills the caller's sheet and fires OnReadDataEvent itself because
     * the original query was prevented.
     * Each behavior's logbook references the caller's sheet as "Base data", showing
     * the combined page once finalized, followed by its own numbered source sheets
     * ("Union 1", "Union 2", etc.). No separate logging snapshot is made.
     *
     * @param OnBeforeReadDataEvent $event
     * @throws BehaviorRuntimeError
     * @return void
     */
    public function onBeforeReadDoUnion(OnBeforeReadDataEvent $event) : void
    {
        // Ignore reads that do not belong to this behavior or were not prepared for a union.
        if (! $this->matches($event) || ! isset(self::$pendingReads[$event])) {
            return;
        }
        // Only the first registered union behavior coordinates the combined read.
        $unionBehaviors = self::$pendingReads[$event];
        if ($unionBehaviors[0] !== $this) {
            return;
        }
        // Claim the prepared request so later listeners cannot execute it again.
        unset(self::$pendingReads[$event]);
        $logbooks = [];
        try {
            $sheet = $event->getDataSheet();
            // Start logging and mark every union behavior as active before reading any source.
            foreach ($unionBehaviors as $unionBehavior) {
                $logbook = $unionBehavior->createLogBook($event);
                $logbook->addDataSheet('Base data', $sheet);
                $logbooks[] = $logbook;
                // Reject a source chain that leads back to an already active union behavior.
                if (! $unionBehavior->beginWork($event, $logbook)) {
                    throw new BehaviorRuntimeError($unionBehavior, 'Cyclic union read detected.', null, null, $logbook);
                }
            }

            // Read from the start of each source through the page end, plus one row for has-more detection.
            $limit = $event->getLimit();
            $offset = $event->getOffset();
            $candidateLimit = $limit > 0 ? $offset + $limit + 1 : 0;
            // Collect candidates separately without changing the caller's pagination; zero means unbounded.
            $result = $sheet->copy()->removeRows();
            $result->setRowsLimit($candidateLimit)->setRowsOffset(0);
            $result->setCounterForRowsInDataSource(0);
            // Include the base object's own rows only if it has a readable source.
            if ($this->getObject()->isReadable()) {
                $logbooks[0]->addLine('Reading native base rows on a separate sheet.');
                // Skip only UnionBehavior on this private sheet; other read listeners still run.
                self::$nativeReadSheets = self::$nativeReadSheets ?? new WeakMap();
                self::$nativeReadSheets[$result] = true;
                try {
                    $result->dataRead();
                } finally {
                    // Remove the bypass marker even when the base read fails.
                    unset(self::$nativeReadSheets[$result]);
                }
            }
            // Let each union behavior read, map and append its configured sources, keeping duplicates.
            foreach ($unionBehaviors as $index => $unionBehavior) {
                $unionBehavior->appendUnionRows($result, $candidateLimit, $logbooks[$index]);
            }

            // Sort all candidates together, select the requested page and fill the caller's sheet.
            $this->finalizeRead($result, $sheet, $limit, $offset);
            // Complete each behavior's logging and lifecycle events.
            foreach ($unionBehaviors as $index => $unionBehavior) {
                $unionBehavior->finishWork($event, $logbooks[$index]);
            }
            // Keep the original read suppressed and notify listeners that the combined result is ready.
            $event->preventRead(true);
            $this->getWorkbench()->eventManager()->dispatch(new OnReadDataEvent($sheet, $limit, $offset, $sheet->countRows()));
        } catch (\Throwable $error) {
            // Preserve the cause and the coordinator's logbook for debugging.
            throw new BehaviorRuntimeError($this, 'Union read failed: ' . $error->getMessage(), null, $error, $logbooks[0] ?? null);
        } finally {
            // Always clear active flags so later reads can run, including after a failure.
            foreach ($unionBehaviors as $unionBehavior) {
                $unionBehavior->isInProgress = false;
            }
        }
    }

    /**
     * Append this behavior's sources to the shared candidate sheet without pagination.
     *
     * Preserve duplicates and combine source counts only when every count is known.
     * All source reads use their own pagination properties, leaving the caller's
     * sheet and event pagination untouched.
     *
     * @param DataSheetInterface $sheet
     * @param int $limit
     * @param DataLogBookInterface $logbook
     * @return void
     */
    private function appendUnionRows(DataSheetInterface $sheet, int $limit, DataLogBookInterface $logbook) : void
    {
        $total = $sheet->countRowsInDataSource();
        $unionNumber = 0;
        foreach ($this->getUnionMappings() as $union) {
            $source = $union->createUnionSheet($sheet, $logbook);
            $logbook->addLine('Reading union object ' . $source->getMetaObject()->__toString() . '.');
            $source->setRowsLimit($limit)->setRowsOffset(0);
            $logbook->addDataSheet('Union ' . ++$unionNumber, $source);
            $source->dataRead();
            $sourceTotal = $source->countRowsInDataSource();
            $total = $total !== null && $sourceTotal !== null ? $total + $sourceTotal : null;
            $mapped = $union->mapToBase($source, $sheet, $logbook);
            $sheet->addRows($mapped->getRows(), false, false);
            $logbook->addLine('Appended ' . $mapped->countRows() . ' rows.');
        }
        $sheet->setCounterForRowsInDataSource($total);
    }

    /**
     * Sort all contributions before applying the original request's offset and limit once.
     *
     * Source sheets read an extra candidate to detect another page without requiring
     * a count. Keep the full source count separate from the page's actual row count,
     * and retain the original sheet's column names and pagination settings.
     *
     * @param DataSheetInterface $result
     * @param DataSheetInterface $sheet
     * @param int|null $limit
     * @param int $offset
     * @return void
     */
    private function finalizeRead(DataSheetInterface $result, DataSheetInterface $sheet, ?int $limit, int $offset) : void
    {
        $total = $result->countRowsInDataSource();
        $result->sort();
        $hasMore = $limit > 0 && $result->countRows() > $offset + $limit;
        $rows = array_slice($result->getRows(), $offset, $limit > 0 ? $limit : null);
        // Native reads may add system columns required by other behaviors.
        foreach ($result->getColumns() as $column) {
            if (! $sheet->getColumns()->getByExpression($column->getExpressionObj())) {
                $sheet->getColumns()->addFromExpression((string) $column->getExpressionObj(), $column->getName(), $column->getHidden());
            }
        }
        $sheet->removeRows()->addRows($rows, false, false);
        $sheet->setCounterForRowsInDataSource($total);
        $sheet->setPaged($hasMore);
        $sheet->setFresh(true);
    }

    /**
     * Reject writes instead of silently changing only the native base rows.
     *
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function onWriteThrowError(DataSheetEventInterface $event) : void
    {
        if ($this->matches($event)) {
            throw new BehaviorRuntimeError($this, 'UnionBehavior does not support updates or deletes.');
        }
    }

    /**
     * Inherited sources must not trigger the parent's union behavior.
     *
     * @param DataSheetEventInterface $event
     * @return bool
     */
    private function matches(DataSheetEventInterface $event) : bool
    {
        return ! $this->isDisabled()
            && ! isset(self::$nativeReadSheets[$event->getDataSheet()])
            && ($this->getUnionBaseObject() === null || $this->getUnionBaseObject()->isExactly($this->getObject()))
            && $event->getDataSheet()->getMetaObject()->isExactly($this->getObject());
    }
}