<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\Behaviors\UnionBehavior;
use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\DataSheets\Mappings\DataColumnMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\DataFilterToFilterMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\DataSorterToSorterMapping;
use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Adds the readable rows of one object to a UnionBehavior.
 * 
 * Each entry in the behavior's `union_objects` describes one **union object**
 * whose rows are appended to the **base object** carrying the behavior. Both
 * objects may use different data sources. Rows are appended without removing
 * duplicates, like SQL UNION ALL; no relation between the objects is needed.
 * 
 * ## Inheriting objects
 * 
 * If the union object extends the base object in the metamodel, its inherited
 * attributes have the same aliases and meanings. Only `object_alias` is required:
 * column, filter and sorter mappings are generated using matching aliases,
 * including attributes reached through inherited relation paths.
 * 
 * For example, if `my.App.SEARCH_RESULT` extends `exface.Core.SEARCH_RESULT`,
 * add this entry to the behavior on `exface.Core.SEARCH_RESULT`:
 * 
 * ```json
 * {
 *     "object_alias": "my.App.SEARCH_RESULT"
 * }
 * 
 * ```
 * 
 * This is object inheritance, not a relation through a foreign key. Inherited
 * copies of the parent's UnionBehavior do not run on the child. Configure a
 * separate behavior on the child if it should itself combine additional objects.
 * 
 * ## Examples
 * 
 * ### Unrelated objects with renamed columns and a source label
 * 
 * For objects that do not extend the base object, define `union_to_base_mapper`
 * explicitly, even if some aliases happen to match. It translates source values
 * into the common result structure. Unlike SQL UNION, matching is by expressions,
 * not by column order.
 * 
 * Suppose the base object `my.App.TASK` provides `UID`, `NAME`, `DUE_ON` and
 * `SOURCE`, while an order app stores requests with different attribute aliases:
 * 
 * ```json
 * {
 *     "object_alias": "my.OrderApp.ORDER_REQUEST",
 *     "union_to_base_mapper": {
 *         "column_to_column_mappings": [
 *             {"from": "REQUEST_ID", "to": "UID"},
 *             {"from": "TITLE", "to": "NAME"},
 *             {"from": "REQUIRED_ON", "to": "DUE_ON"},
 *             {"from": "'Order request'", "to": "SOURCE"}
 *         ]
 *     }
 * }
 * 
 * ```
 * 
 * The `from` expressions belong to the order request; the `to` expressions belong
 * to the task. The quoted expression `'Order request'` supplies the same source
 * label for every row. Each requested result column needs exactly one mapping;
 * mappings for columns not requested by the read are not executed or read.
 * Designers must ensure mapped UIDs are unique across all contributing objects.
 * 
 * Attribute-to-attribute mappings also translate filters and sorters in the
 * opposite direction automatically. In this example, a filter on `NAME` becomes
 * a filter on `TITLE` with the same comparator and value; sorting by `DUE_ON`
 * becomes sorting by `REQUIRED_ON` with the same direction. Nested AND/OR filter
 * groups retain their structure.
 * 
 * ### Override derived filter and sorter mappings
 * 
 * Add `base_to_union_mapper` when a source requires special rules. Its direction
 * is the reverse of the result mapper: `from` refers to the base object and `to`
 * to the union object. Explicit rules take precedence over derived rules.
 * 
 * For example, translate an exact task-name filter into a contains search on the
 * request title, and sort names by a dedicated source sorting attribute:
 * 
 * ```json
 * {
 *     "object_alias": "my.OrderApp.ORDER_REQUEST",
 *     "union_to_base_mapper": {
 *         "column_to_column_mappings": [
 *             {"from": "REQUEST_ID", "to": "UID"},
 *             {"from": "TITLE", "to": "NAME"},
 *             {"from": "REQUIRED_ON", "to": "DUE_ON"},
 *             {"from": "'Order request'", "to": "SOURCE"}
 *         ]
 *     },
 *     "base_to_union_mapper": {
 *         "filter_to_filter_mappings": [
 *             {"from": "NAME", "from_comparator": "==", "to": "TITLE", "to_comparator": "="}
 *         ],
 *         "sorter_to_sorter_mappings": [
 *             {"from": "NAME", "to": "TITLE_SORT"}
 *         ]
 *     }
 * }
 * 
 * ```
 * 
 * Only `NAME` filters using `==` use the explicit comparator rule; other
 * comparators still use the derived mapping to `TITLE`. Filters on `DUE_ON`
 * and sorters without explicit overrides are also derived as before.
 * Sorter mappings preserve the requested direction unless `to_direction` is
 * explicitly set. For paginated reads, a custom source sorter must produce the
 * same ordering as the final base-column sort; otherwise limiting the source
 * read can omit rows that belong on the final page. In this example, `TITLE_SORT`
 * must order rows consistently with the displayed `TITLE`.
 * 
 * ## Limitations
 * 
 * - Source-to-base mappers currently support `column_to_column_mappings` only.
 * - Constants and formulas do not automatically provide reverse filter or sorter
 *   mappings. Filtering or sorting a result attribute mapped from such an
 *   expression requires an explicit base-to-union rule; otherwise it is an error.
 * - Related attributes require a union object that extends the base object.
 * - Aggregations and totals are not supported.
 * - Missing mappings for requested columns, filters or sorters are errors.
 * - Selecting only constants requires a source UID attribute to retain the number
 *   of source rows.
 */
class UnionMapping implements iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    use ICanBeConvertedToUxonTrait;

    private UnionBehavior $behavior;
    private ?string $objectAlias = null;
    private ?UxonObject $unionToBaseMapper = null;
    private ?UxonObject $baseToUnionMapper = null;

    /**
     * @param UnionBehavior $behavior
     * @param UxonObject $uxon
     */
    public function __construct(UnionBehavior $behavior, UxonObject $uxon)
    {
        $this->behavior = $behavior;
        $this->importUxonObject($uxon);
    }

    /**
     * The object whose rows are appended to the base object.
     *
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     * @uxon-required true
     * @param string $alias
     * @return UnionMapping
     */
    public function setObjectAlias(string $alias) : UnionMapping
    {
        $this->objectAlias = $alias;
        return $this;
    }

    /**
     * Map source columns to columns of the behavior's object.
     *
     * @uxon-property union_to_base_mapper
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     * @uxon-template {"column_to_column_mappings": [{"from": "", "to": ""}]}
     * 
     * @param UxonObject $uxon
     * @return UnionMapping
     */
    public function setUnionToBaseMapper(UxonObject $uxon) : UnionMapping
    {
        $this->unionToBaseMapper = $uxon;
        return $this;
    }

    /**
     * Override derived filter and sorter mappings, in the base-to-source direction.
     *
     * Column mappings here can also add source columns required by custom filters.
     *
     * @uxon-property base_to_union_mapper
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheetMapper
     * @uxon-template {"filter_to_filter_mappings": [{"from": "", "to": ""}], "sorter_to_sorter_mappings": [{"from": "", "to": ""}]}
     * 
     * @param UxonObject $uxon
     * @return UnionMapping
     */
    public function setBaseToUnionMapper(UxonObject $uxon) : UnionMapping
    {
        $this->baseToUnionMapper = $uxon;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench() : WorkbenchInterface
    {
        return $this->behavior->getWorkbench();
    }

    /**
     * @return MetaObjectInterface
     */
    public function getObject() : MetaObjectInterface
    {
        if ($this->objectAlias === null || $this->objectAlias === '') {
            throw new BehaviorConfigurationError($this->behavior, 'Every union_objects entry requires object_alias.');
        }
        return MetaObjectFactory::createFromString($this->getWorkbench(), $this->objectAlias);
    }

    /**
     * Build a fresh mapper, retaining only requested result columns.
     *
     * @param DataSheetInterface $baseSheet
     * @return DataSheetMapperInterface
     */
    public function getMapperFromUnionToBaseObject(DataSheetInterface $baseSheet) : DataSheetMapperInterface
    {
        $source = $this->getObject();
        $base = $this->behavior->getObject();
        $mapper = $this->createMapper($source, $base);
        // Without an explicit mapper, getColumnMappings() verifies that the source extends
        // the base (or is the base) and generates same-alias mappings for inherited attributes.
        // Unrelated sources must supply their own mappings; both cases are handled below.
        $available = $this->getColumnMappings($baseSheet);
        // Keep only requested result columns, including for inheriting sources, so unused
        // inherited attributes and filter/sorter-only expressions are not read as result data.
        foreach ($baseSheet->getColumns() as $column) {
            $expression = $column->getExpressionObj();
            $this->assertSupportedExpression($expression);
            $matches = array_filter($available, fn(DataColumnMapping $mapping) =>
                strcasecmp((string) $mapping->getToExpression(), (string) $expression) === 0
            );
            if (count($matches) !== 1) {
                throw new BehaviorConfigurationError($this->behavior, 'Union requires exactly one column mapping for "' . $expression . '".');
            }
            $mapping = reset($matches);
            $this->assertSupportedExpression($mapping->getFromExpression());
            $mapper->addMapping($mapping);
        }
        return $mapper;
    }

    /**
     * Build filter and sorter mappings, deriving missing rules from column mappings.
     *
     * @param DataSheetInterface $baseSheet
     * @return DataSheetMapperInterface
     */
    public function getMapperFromBaseToUnionObject(DataSheetInterface $baseSheet) : DataSheetMapperInterface
    {
        // Inheriting sources do not require a separate request mapper: their generated
        // same-alias column mappings below also provide the reverse filter/sorter rules.
        // An explicit request mapper can override those defaults for either kind of source.
        $mapper = $this->baseToUnionMapper === null
            ? $this->createMapper($this->behavior->getObject(), $this->getObject())
            : DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $this->baseToUnionMapper->copy(), $this->behavior->getObject(), $this->getObject());
        $this->assertMapperObjects($mapper, $this->behavior->getObject(), $this->getObject());
        $mapper->setInheritColumns(false)->setInheritFilters(false)->setInheritSorters(false)->setInheritEmptyData(true);
        $mapper->setRefreshDataAfterMapping(false);
        // Reuse the inheritance-aware column mappings, including filter/sorter-only aliases.
        // Reverse source-to-base into base-to-source; inherited aliases remain unchanged,
        // while unrelated sources translate the base alias back to their own attribute.
        foreach ($this->getColumnMappings($baseSheet) as $mapping) {
            $from = $mapping->getToExpression();
            $to = $mapping->getFromExpression();
            // Constants and formulas cannot supply automatic reverse attribute mappings.
            if (! $from->isMetaAttribute() || ! $to->isMetaAttribute()) {
                continue;
            }
            // Append filter defaults after explicit rules so matching overrides win;
            // comparator-specific overrides still leave defaults for other comparators.
            $config = new UxonObject(['from' => (string) $from, 'to' => (string) $to, 'prevent_inheriting_filter' => false]);
            $mapper->addMapping(new DataFilterToFilterMapping($mapper, $config));
            if (! $this->findMapping($mapper, DataSorterToSorterMapping::class, (string) $from)) {
                $mapper->addMapping(new DataSorterToSorterMapping($mapper, new UxonObject(['from' => (string) $from, 'to' => (string) $to])));
            }
        }
        return $mapper;
    }

    /**
     * Map the read request without copying rows or reading missing data.
     *
     * Nested condition groups retain their operators and grouping.
     *
     * @param DataSheetInterface $baseSheet
     * @param LogBookInterface|null $logbook
     * @return DataSheetInterface
     */
    public function createUnionSheet(DataSheetInterface $baseSheet, ?LogBookInterface $logbook = null) : DataSheetInterface
    {
        // Resolve result mappings to discover source columns and reverse mappings for the request.
        $toBase = $this->getMapperFromUnionToBaseObject($baseSheet);
        $toUnion = $this->getMapperFromBaseToUnionObject($baseSheet);
        // Map only the schema: existing base rows must not become source data. Filters and
        // sorters are translated separately below to preserve grouping and rule precedence.
        $schema = $baseSheet->copy()->removeRows();
        $schema->getFilters()->removeAll();
        $schema->getSorters()->removeAll();
        // Disable missing-data reads while constructing the source request.
        $sheet = $toUnion->map($schema, false, $logbook);
        // Select the source expressions needed to populate the requested base columns.
        foreach ($toBase->getMappings() as $mapping) {
            foreach ($mapping->getRequiredExpressions($sheet) as $expression) {
                // Constants are supplied during result mapping and require no source column.
                if (! $expression->isStatic()) {
                    $sheet->getColumns()->addFromExpression($expression);
                }
            }
        }
        // Rebase each condition recursively while retaining its enclosing AND/OR groups.
        $conditionFilter = function(ConditionInterface $condition) use ($toUnion, $baseSheet, $sheet, $logbook) {
            // Ignore empty conditions that are configured to be ignored; otherwise map them.
            if ($condition->isEmpty() && $condition->willIgnoreEmptyValues()) {
                return null;
            }
            $expression = $condition->getExpression();
            $this->assertSupportedExpression($expression);
            $mapping = $this->findMapping($toUnion, DataFilterToFilterMapping::class, (string) $expression, $condition->getComparator());
            // Never drop an unmapped condition: that would broaden the source read.
            if ($mapping === null) {
                throw new BehaviorConfigurationError($this->behavior, 'No union filter mapping for "' . $expression . '" with comparator "' . $condition->getComparator() . '".');
            }
            $this->assertSupportedExpression($mapping->getToExpression());
            // Apply one rule to an isolated copy; filter mappings may remove their input.
            $from = DataSheetFactory::createFromObject($baseSheet->getMetaObject());
            $from->getFilters()->addCondition($condition->copy());
            $to = DataSheetFactory::createFromObject($sheet->getMetaObject());
            $mapping->map($from, $to, $logbook);
            $conditions = $to->getFilters()->getConditions();
            // Each original condition must have exactly one replacement in the same group.
            if (count($conditions) !== 1) {
                throw new BehaviorRuntimeError($this->behavior, 'Cannot apply filters to UNION. Found ' . count($conditions) . ' replacement conditions for `' . $condition->__toString() . '`.');
            }
            return reset($conditions);
        };
        $sheet->setFilters($baseSheet->getFilters()->rebaseCustom($sheet->getMetaObject(), $conditionFilter));
        // Translate sorters in request order, applying only the first matching rule to each.
        foreach ($baseSheet->getSorters() as $sorter) {
            $mapping = $this->findMapping($toUnion, DataSorterToSorterMapping::class, $sorter->getAttributeAlias());
            // Missing source ordering would make limited reads unsuitable for global pagination.
            if ($mapping === null) {
                throw new BehaviorConfigurationError($this->behavior, 'No union sorter mapping for "' . $sorter->getAttributeAlias() . '".');
            }
            $this->assertSupportedExpression($mapping->getToExpression());
            $from = DataSheetFactory::createFromObject($baseSheet->getMetaObject());
            $from->getSorters()->addFromString($sorter->getAttributeAlias(), $sorter->getDirection());
            $mapping->map($from, $sheet, $logbook);
        }
        // Keep the translated base ordering rather than adding source-specific defaults.
        $sheet->setAutoSort(false);
        $sheet->setAutoCount($baseSheet->getAutoCount());
        // A constant-only projection still needs a physical source column to read one row
        // per source record; the UID provides that anchor without entering the result mapping.
        if ($sheet->getColumns()->isEmpty()) {
            // Without a UID there is no supported fallback for preserving source cardinality.
            if (! $sheet->getMetaObject()->hasUidAttribute()) {
                throw new BehaviorConfigurationError($this->behavior, 'A union selecting only constants requires a source UID attribute to preserve row counts.');
            }
            $sheet->getColumns()->addFromUidAttribute();
        }
        return $sheet;
    }

    /**
     * Preserve row cardinality, constants and custom base column names.
     *
     * @param DataSheetInterface $unionSheet
     * @param DataSheetInterface $baseSheet
     * @param LogBookInterface|null $logbook
     * @return DataSheetInterface
     */
    public function mapToBase(DataSheetInterface $unionSheet, DataSheetInterface $baseSheet, ?LogBookInterface $logbook = null) : DataSheetInterface
    {
        $mapper = $this->getMapperFromUnionToBaseObject($baseSheet);
        $result = DataSheetFactory::createFromObject($baseSheet->getMetaObject());
        foreach ($baseSheet->getColumns() as $column) {
            $result->getColumns()->addFromExpression((string) $column->getExpressionObj());
        }
        $emptyRow = array_fill_keys(array_keys($result->getColumns()->getAll()), null);
        $result->addRows(array_fill(0, $unionSheet->countRows(), $emptyRow));
        foreach ($mapper->getMappings() as $mapping) {
            $mapping->setCreateRowInEmptyData(false);
            $mapping->map($unionSheet, $result, $logbook);
        }
        // Column mappings use expression-derived names; the caller may use custom names.
        $rows = [];
        foreach ($result->getRows() as $row) {
            $mapped = [];
            foreach ($baseSheet->getColumns() as $column) {
                $mappedColumn = $result->getColumns()->getByExpression($column->getExpressionObj());
                $mapped[$column->getName()] = $row[$mappedColumn->getName()] ?? null;
            }
            $rows[] = $mapped;
        }
        return $baseSheet->copy()->removeRows()->addRows($rows, false, false);
    }

    /**
     * Resolve source-to-base column mappings for the current read request.
     * 
     * Use the explicit union_to_base_mapper when configured. Otherwise require the
     * source to be the base object or extend it, and generate same-expression mappings
     * for requested columns and expressions used in filters or sorters. Filter-only
     * and sorter-only expressions are included so reverse request mappings can also
     * be derived. These mappings are not yet pruned to the selected result columns.
     * Reject mismatched mapper objects and mapping types other than DataColumnMapping.
     * 
     * @param DataSheetInterface $baseSheet
     * @return DataColumnMapping[]
     */
    private function getColumnMappings(DataSheetInterface $baseSheet) : array
    {
        $source = $this->getObject();
        $base = $this->behavior->getObject();
        if ($this->unionToBaseMapper === null) {
            if (! $source->is($base)) {
                throw new BehaviorConfigurationError($this->behavior, 'Unrelated union objects require union_to_base_mapper.');
            }
            $mapper = $this->createMapper($source, $base);
            $expressions = [];
            foreach ($baseSheet->getColumns() as $column) {
                $expressions[] = (string) $column->getExpressionObj();
            }
            foreach ($baseSheet->getFilters()->getConditionsRecursive() as $condition) {
                $expressions[] = (string) $condition->getExpression();
            }
            foreach ($baseSheet->getSorters() as $sorter) {
                $expressions[] = $sorter->getAttributeAlias();
            }
            foreach (array_unique($expressions) as $expression) {
                $mapper->addMapping(new DataColumnMapping($mapper, new UxonObject(['from' => $expression, 'to' => $expression])));
            }
        } else {
            $mapper = DataSheetMapperFactory::createFromUxon($this->getWorkbench(), $this->unionToBaseMapper->copy(), $source, $base);
        }
        $this->assertMapperObjects($mapper, $source, $base);
        foreach ($mapper->getMappings() as $mapping) {
            if (get_class($mapping) !== DataColumnMapping::class) {
                throw new BehaviorConfigurationError($this->behavior, 'union_to_base_mapper currently supports column_to_column_mappings only.');
            }
        }
        return $mapper->getMappings();
    }

    /**
     * Create an empty mapper with explicit source and target objects.
     * 
     * Disable automatic column, filter and sorter inheritance so only the union's
     * explicit or generated mappings control the result. Preserve empty input data
     * so constants cannot manufacture rows when the source has none.
     * 
     * @param MetaObjectInterface $from
     * @param MetaObjectInterface $to
     * @return DataSheetMapper
     */
    private function createMapper(MetaObjectInterface $from, MetaObjectInterface $to) : DataSheetMapper
    {
        $mapper = new DataSheetMapper($this->getWorkbench());
        $mapper->setFromMetaObject($from)->setToMetaObject($to);
        $mapper->setInheritColumns(false)->setInheritFilters(false)->setInheritSorters(false)->setInheritEmptyData(true);
        return $mapper;
    }

    /**
     * Require the mapper's direction to match the configured union endpoints exactly.
     * 
     * An explicitly configured mapper must not substitute a different object, even
     * an inheriting one, for either endpoint. Reject mismatches as behavior
     * configuration errors before applying any mappings.
     * 
     * @param DataSheetMapperInterface $mapper
     * @param MetaObjectInterface $from
     * @param MetaObjectInterface $to
     * @return void
     */
    private function assertMapperObjects(DataSheetMapperInterface $mapper, MetaObjectInterface $from, MetaObjectInterface $to) : void
    {
        if (! $mapper->getFromMetaObject()->isExactly($from) || ! $mapper->getToMetaObject()->isExactly($to)) {
            throw new BehaviorConfigurationError($this->behavior, 'Union mapper from/to objects do not match the union configuration.');
        }
    }

    /**
     * Allow related expressions only when inheritance preserves their relation paths.
     * 
     * For unrelated objects, check every required attribute, including formula
     * dependencies, against the expression's own object and reject related data.
     * 
     * @param ExpressionInterface $expression
     * @return void
     */
    private function assertSupportedExpression(ExpressionInterface $expression) : void
    {
        if ($this->getObject()->is($this->behavior->getObject())) {
            return;
        }
        foreach ($expression->getRequiredAttributes() as $alias) {
            if ($expression->getMetaObject()->getAttribute($alias)->isRelated()) {
                throw new BehaviorConfigurationError($this->behavior, 'Union does not support related data: "' . $expression . '".');
            }
        }
    }

    /**
     * Find the first mapping matching the requested type, source expression and comparator.
     * 
     * Compare expression strings case-insensitively. When a comparator is supplied,
     * accept rules with that from_comparator or no comparator restriction. Search in
     * mapper order so explicit rules placed before generated rules take precedence.
     * Return NULL when no applicable mapping exists.
     * 
     * @param DataSheetMapperInterface $mapper
     * @param string $class
     * @param string $expression
     * @param string|null $comparator
     * @return DataColumnMapping|null
     */
    private function findMapping(DataSheetMapperInterface $mapper, string $class, string $expression, ?string $comparator = null) : ?DataColumnMapping
    {
        foreach ($mapper->getMappings() as $mapping) {
            if (! ($mapping instanceof $class) || strcasecmp((string) $mapping->getFromExpression(), $expression) !== 0) {
                continue;
            }
            $configuredComparator = $mapping->exportUxonObject()->getProperty('from_comparator');
            if ($comparator !== null && $configuredComparator !== null && $configuredComparator !== '' && $configuredComparator !== $comparator) {
                continue;
            }
            return $mapping;
        }
        return null;
    }
}