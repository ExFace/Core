<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\Behaviors\UnionBehavior;
use exface\Core\CommonLogic\ContextManager;
use exface\Core\CommonLogic\DataManager;
use exface\Core\CommonLogic\DataTransaction;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataConnectors\TransparentConnector;
use exface\Core\DataConnectors\FileContentsConnector;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\QueryBuilders\DummyQueryBuilder;
use exface\Core\QueryBuilders\CsvBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Exercises union reads with a real Workbench and temporary, database-free objects.
 * Source event handlers supply deterministic data at the external read boundary.
 */
class UnionBehaviorTest extends TestCase
{
    private Workbench $workbench;

    /** @var UnionBehavior[] */
    private array $behaviors = [];

    /** @var string[] */
    private array $temporaryFiles = [];

    /**
     * {@inheritDoc}
     * @see TestCase::setUp()
     */
    protected function setUp() : void
    {
        $this->workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'union-unit-test']);
        $this->workbench->getConfig()->loadConfigFile(dirname(__DIR__, 3) . '\\Config\\exface.Core.config.json');
        $model = new ReflectionProperty(Workbench::class, 'mm');
        $model->setAccessible(true);
        $model->setValue($this->workbench, new Model($this->workbench));
        $context = new ReflectionProperty(Workbench::class, 'context');
        $context->setAccessible(true);
        $context->setValue($this->workbench, new ContextManager($this->workbench));
    }

    /**
     * {@inheritDoc}
     * @see TestCase::tearDown()
     */
    protected function tearDown() : void
    {
        foreach ($this->behaviors as $behavior) {
            $behavior->disable();
        }
        MetaObjectFactory::clearCache(null, true, true);
        foreach ($this->temporaryFiles as $path) {
            unlink($path);
        }
    }

    /**
     * Multiple behaviors must retain earlier candidates until global pagination.
     *
     * @return void
     */
    public function testCombinesNativeRowsAndMultipleBehaviorsBeforePagination() : void
    {
        $base = $this->object(['UID', 'NAME'], true);
        $first = $this->object(['UID', 'NAME']);
        $second = $this->object(['UID', 'NAME']);
        $this->behavior($base, [$this->entry($first)]);
        $this->behavior($base, [$this->entry($second)]);
        $this->source($base, [['UID' => 'b', 'NAME' => 'B'], ['UID' => 'e', 'NAME' => 'E']]);
        $this->source($first, [['UID' => 'a', 'NAME' => 'A'], ['UID' => 'd', 'NAME' => 'D']]);
        $this->source($second, [['UID' => 'c', 'NAME' => 'C'], ['UID' => 'f', 'NAME' => 'F']]);
        $sheet = $this->sheet($base);
        $sheet->getSorters()->addFromString('NAME');
        $sheet->setRowsLimit(2)->setRowsOffset(2);

        $this->assertSame(2, $sheet->dataRead());
        $this->assertSame(['C', 'D'], $sheet->getColumnValues('NAME'));
        $this->assertSame(6, $sheet->countRowsInDataSource());
        $this->assertTrue($sheet->isPaged());
        $this->assertSame(2, $sheet->getRowsOffset());
        $this->assertSame(2, $sheet->getRowsLimit());
        $sheet->setRowsOffset(4);
        $this->assertSame(2, $sheet->dataRead());
        $this->assertSame(['E', 'F'], $sheet->getColumnValues('NAME'));
        $this->assertFalse($sheet->isPaged());
    }

    /**
     * Coordinate one native read and one completed event without changing caller pagination.
     *
     * @return void
     */
    public function testReplacementReadOwnsLifecycleAndPreservesEventPagination() : void
    {
        $base = $this->object(['UID', 'NAME'], true);
        $first = $this->object(['UID', 'NAME']);
        $second = $this->object(['UID', 'NAME']);
        $this->behavior($base, [$this->entry($first)]);
        $this->behavior($base, [$this->entry($second)]);
        $this->source($base, [['UID' => 'b', 'NAME' => 'B'], ['UID' => 'e', 'NAME' => 'E']]);
        $this->source($first, [['UID' => 'a', 'NAME' => 'A'], ['UID' => 'd', 'NAME' => 'D']]);
        $this->source($second, [['UID' => 'c', 'NAME' => 'C'], ['UID' => 'f', 'NAME' => 'F']]);
        $sheet = $this->sheet($base);
        $sheet->getSorters()->addFromString('NAME');
        $before = [];
        $after = [];
        $this->workbench->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), function(OnBeforeReadDataEvent $event) use ($base, &$before) {
            if ($event->getDataSheet()->getMetaObject()->isExactly($base)) {
                $before[] = $event;
            }
        }, -100);
        $this->workbench->eventManager()->addListener(OnReadDataEvent::getEventName(), function(OnReadDataEvent $event) use ($base, &$after) {
            if ($event->getDataSheet()->getMetaObject()->isExactly($base)) {
                $after[] = [$event, $event->getDataSheet()->getColumnValues('NAME')];
            }
        });

        $this->assertSame(2, $sheet->dataRead(2, 2));
        $this->assertCount(2, $before);
        $this->assertSame($sheet, $before[0]->getDataSheet());
        $this->assertSame(2, $before[0]->getLimit());
        $this->assertSame(2, $before[0]->getOffset());
        $this->assertTrue($before[0]->isReadPerformed());
        $this->assertNotSame($sheet, $before[1]->getDataSheet());
        $this->assertSame(5, $before[1]->getLimit());
        $this->assertSame(0, $before[1]->getOffset());
        $this->assertCount(2, $after);
        $this->assertSame($before[1]->getDataSheet(), $after[0][0]->getDataSheet());
        $this->assertSame(['B', 'E'], $after[0][1]);
        $this->assertSame($sheet, $after[1][0]->getDataSheet());
        $this->assertSame(['C', 'D'], $after[1][1]);
        $this->assertSame(2, $after[1][0]->getLimit());
        $this->assertSame(2, $after[1][0]->getOffset());
        $this->assertSame(2, $after[1][0]->getAffectedRowsCount());
    }

    /**
     * A failed native read must release the bypass marker and all participants' busy flags.
     *
     * @return void
     */
    public function testNativeReadFailureDoesNotLeaveUnionProcessingBypassed() : void
    {
        $base = $this->object(['UID', 'NAME'], true);
        $source = $this->object(['UID', 'NAME']);
        $behavior = $this->behavior($base, [$this->entry($source)]);
        $sheet = $this->sheet($base);
        $failedNativeSheet = null;
        $this->workbench->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), function(OnBeforeReadDataEvent $event) use ($base, &$failedNativeSheet) {
            if (! $event->isPreventRead() && $event->getDataSheet()->getMetaObject()->isExactly($base) && $failedNativeSheet === null) {
                $failedNativeSheet = $event->getDataSheet();
                throw new \RuntimeException('Native source unavailable.');
            }
        }, -100);
        $this->source($base, [['UID' => 'b', 'NAME' => 'Base']]);
        $this->source($source, [['UID' => 's', 'NAME' => 'Source']]);

        try {
            $sheet->dataRead();
            $this->fail('Expected the native source error to propagate.');
        } catch (BehaviorRuntimeError $error) {
            $this->assertStringContainsString('Native source unavailable.', $error->getMessage());
        }
        $this->assertFalse($behavior->isInProgress());
        $this->assertInstanceOf(DataSheetInterface::class, $failedNativeSheet);
        $this->assertNotSame($sheet, $failedNativeSheet);
        $this->assertSame(2, $failedNativeSheet->dataRead());
        $this->assertSame(['Base', 'Source'], $failedNativeSheet->getColumnValues('NAME'));
        $this->assertSame(2, $sheet->dataRead());
        $this->assertSame(['Base', 'Source'], $sheet->getColumnValues('NAME'));
    }

    /**
     * Generic replacement readers own completion events; DataSheet must not dispatch another.
     *
     * @return void
     */
    public function testExternalReadDispatchesItsOwnCompletionWithoutUnionBehavior() : void
    {
        $object = $this->object(['UID', 'NAME'], true);
        $this->source($object, [['UID' => '1', 'NAME' => 'Native']]);
        $sheet = $this->sheet($object);
        $completions = [];
        $this->workbench->eventManager()->addListener(OnReadDataEvent::getEventName(), function(OnReadDataEvent $event) use (&$completions) {
            $completions[] = $event;
        });

        $this->assertSame(1, $sheet->dataRead());
        $this->assertCount(1, $completions);
        $this->assertSame($sheet, $completions[0]->getDataSheet());
        $this->assertSame(['Native'], $sheet->getColumnValues('NAME'));
    }

    /**
     * Preventing a read without reporting external completion retains the zero return value.
     *
     * @return void
     */
    public function testPreventedReadDoesNotInventACompletionEvent() : void
    {
        $object = $this->object(['UID', 'NAME']);
        $sheet = $this->sheet($object);
        $completions = 0;
        $this->workbench->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), function(OnBeforeReadDataEvent $event) {
            $event->getDataSheet()->addRow(['UID' => '1', 'NAME' => 'Existing']);
            $event->preventRead();
        });
        $this->workbench->eventManager()->addListener(OnReadDataEvent::getEventName(), function() use (&$completions) {
            $completions++;
        });

        $this->assertSame(0, $sheet->dataRead());
        $this->assertSame(1, $sheet->countRows());
        $this->assertSame(0, $completions);
    }

    /**
     * A virtual base must support nested unions and explicit read arguments.
     *
     * @return void
     */
    public function testCascadingVirtualUnionsAndExplicitPagination() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $middle = $this->object(['UID', 'NAME']);
        $leaf = $this->object(['UID', 'NAME']);
        $this->behavior($base, [$this->entry($middle)]);
        $this->behavior($middle, [$this->entry($leaf)]);
        $this->source($leaf, [['UID' => '1', 'NAME' => 'A'], ['UID' => '2', 'NAME' => 'B'], ['UID' => '3', 'NAME' => 'C']]);
        $sheet = $this->sheet($base);
        $sheet->getSorters()->addFromString('NAME', 'DESC');

        $this->assertSame(1, $sheet->dataRead(1, 1));
        $this->assertSame(['B'], $sheet->getColumnValues('NAME'));
        $this->assertSame(3, $sheet->countRowsInDataSource());
    }

    /**
     * Parent union behaviors must not run again on inherited source objects.
     *
     * @return void
     */
    public function testInheritedObjectMappingsAndInheritedBehaviorCopies() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $child = $this->object(['UID', 'NAME']);
        $behavior = $this->behavior($base, [['object_alias' => $child->getAliasWithNamespace()]]);
        $base->getBehaviors()->add($behavior, 'union', false);
        $child->extendFromObject($base);
        $this->source($child, [['UID' => 'child', 'NAME' => 'Found']]);
        $sheet = $this->sheet($base);
        $sheet->getFilters()->addConditionFromString('NAME', 'Found', '==');

        $this->assertSame(1, $sheet->dataRead());
        $this->assertSame(['Found'], $sheet->getColumnValues('NAME'));
    }

    /**
     * Nested OR groups must not be flattened to AND or mutate the caller's filters.
     *
     * @return void
     */
    public function testMapsNestedFiltersAndSortersAcrossUnrelatedObjects() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['ID', 'TITLE']);
        $entry = $this->entry($source, ['ID' => 'UID', 'TITLE' => 'NAME']);
        $behavior = $this->behavior($base, [$entry]);
        $this->source($source, [
            ['ID' => '1', 'TITLE' => 'A'],
            ['ID' => '2', 'TITLE' => 'B'],
            ['ID' => '3', 'TITLE' => 'C']
        ]);
        $sheet = $this->sheet($base);
        $group = $sheet->getFilters()->addNestedGroupFromString('OR');
        $group->addConditionFromString('NAME', 'A', '==');
        $group->addConditionFromString('NAME', 'C', '==');
        $sheet->getSorters()->addFromString('NAME', 'DESC');
        $filters = $sheet->getFilters()->exportUxonObject()->toJson();

        $this->assertSame(2, $sheet->dataRead());
        $this->assertSame(['C', 'A'], $sheet->getColumnValues('NAME'));
        $this->assertSame($filters, $sheet->getFilters()->exportUxonObject()->toJson());
        $request = $behavior->getUnionMappings()[0]->createUnionSheet($sheet);
        $this->assertTrue($request->isEmpty());
        $this->assertSame('TITLE', $request->getSorters()->getFirst()->getAttributeAlias());
    }

    /**
     * Unrequested mappings must not require missing source attributes.
     *
     * @return void
     */
    public function testPrunesUnusedMappingsAndPreservesCustomColumnNamesAndConstants() : void
    {
        $base = $this->object(['UID', 'NAME', 'SOURCE', 'UNUSED']);
        $source = $this->object(['ID', 'TITLE']);
        $entry = $this->entry($source, ['ID' => 'UID', 'TITLE' => 'NAME', "'Orders'" => 'SOURCE', 'MISSING' => 'UNUSED']);
        $this->behavior($base, [$entry]);
        $this->source($source, [['ID' => '1', 'TITLE' => 'A'], ['ID' => '2', 'TITLE' => 'B']]);
        $sheet = DataSheetFactory::createFromObject($base);
        $sheet->getColumns()->addFromExpression('NAME', 'custom_name');
        $sheet->getColumns()->addFromExpression('SOURCE');

        $this->assertSame(2, $sheet->dataRead());
        $this->assertSame(['A', 'B'], $sheet->getColumnValues('custom_name'));
        $this->assertSame(['Orders', 'Orders'], $sheet->getColumnValues('SOURCE'));
        $this->assertCount(2, $sheet->getColumns());
    }

    /**
     * Explicit filter comparators take precedence; other comparators still derive.
     *
     * @return void
     */
    public function testExplicitFilterAndSorterOverrides() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['UID', 'NAME']);
        $entry = $this->entry($source);
        $entry['base_to_union_mapper'] = [
            'filter_to_filter_mappings' => [['from' => 'NAME', 'from_comparator' => '==', 'to' => 'NAME', 'to_comparator' => '!==']],
            'sorter_to_sorter_mappings' => [['from' => 'NAME', 'to' => 'NAME', 'to_direction' => 'DESC']]
        ];
        $behavior = $this->behavior($base, [$entry]);
        $sheet = $this->sheet($base);
        $sheet->getFilters()->addConditionFromString('NAME', 'A', '==');
        $sheet->getFilters()->addConditionFromString('NAME', 'B', '=');
        $sheet->getSorters()->addFromString('NAME', 'ASC');

        $request = $behavior->getUnionMappings()[0]->createUnionSheet($sheet);
        $conditions = array_values($request->getFilters()->getConditions());
        $this->assertSame('!==', $conditions[0]->getComparator());
        $this->assertSame('=', $conditions[1]->getComparator());
        $this->assertSame('DESC', $request->getSorters()->getFirst()->getDirection());
        $this->assertCount(0, $request->getRows());
    }

    /**
     * Missing requested columns must be explicit configuration errors.
     *
     * @return void
     */
    public function testRejectsUnmappedColumn() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['UID']);
        $behavior = $this->behavior($base, [$this->entry($source, ['UID' => 'UID'])]);
        $this->expectException(BehaviorConfigurationError::class);
        $this->expectExceptionMessage('exactly one column mapping for "NAME"');
        $behavior->getUnionMappings()[0]->createUnionSheet($this->sheet($base));
    }

    /**
     * Filters on unmapped attributes must never be silently discarded.
     *
     * @return void
     */
    public function testRejectsUnmappedFilter() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['UID']);
        $behavior = $this->behavior($base, [$this->entry($source, ['UID' => 'UID'])]);
        $sheet = DataSheetFactory::createFromObject($base);
        $sheet->getColumns()->addFromExpression('UID');
        $sheet->getFilters()->addConditionFromString('NAME', 'A');
        $this->expectException(BehaviorConfigurationError::class);
        $this->expectExceptionMessage('No union filter mapping');
        $behavior->getUnionMappings()[0]->createUnionSheet($sheet);
    }

    /**
     * Cycles must fail explicitly and leave the behavior usable after the error.
     *
     * @return void
     */
    public function testRejectsCyclicUnion() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $behavior = $this->behavior($base, [$this->entry($base)]);
        try {
            $this->sheet($base)->dataRead();
            $this->fail('Expected a cyclic union error.');
        } catch (BehaviorRuntimeError $error) {
            $this->assertStringContainsString('Cyclic union read', $error->getMessage());
        }
        $this->assertFalse($behavior->isInProgress());
        $behavior->setUnionObjects(new UxonObject([]));
        $this->assertSame(0, $this->sheet($base)->dataRead());
    }

    /**
     * Aggregated reads cannot be mistaken for an ordinary union.
     *
     * @return void
     */
    public function testRejectsAggregations() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $behavior = $this->behavior($base, []);
        $sheet = $this->sheet($base);
        $sheet->getAggregations()->addFromString('NAME');
        $this->expectException(BehaviorRuntimeError::class);
        $this->expectExceptionMessage('aggregated sheets');
        $behavior->onBeforeReadPrepare(new OnBeforeReadDataEvent($sheet));
    }

    /**
     * Both update and delete events must be rejected before persistence.
     *
     * @return void
     */
    public function testRejectsUpdateAndDelete() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $behavior = $this->behavior($base, []);
        foreach ([OnBeforeUpdateDataEvent::class, OnBeforeDeleteDataEvent::class] as $eventClass) {
            try {
                $behavior->onWriteThrowError(new $eventClass($this->sheet($base), new DataTransaction(new DataManager($this->workbench))));
                $this->fail('Expected a read-only union error.');
            } catch (BehaviorRuntimeError $error) {
                $this->assertStringContainsString('updates or deletes', $error->getMessage());
            }
        }
    }

    /**
     * Sorter mappings must work through the regular mapper UXON API.
     *
     * @return void
     */
    public function testSorterMappingThroughDataSheetMapper() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['UID', 'TITLE']);
        $mapper = DataSheetMapperFactory::createFromUxon($this->workbench, new UxonObject([
            'inherit_columns' => false,
            'inherit_sorters' => false,
            'sorter_to_sorter_mappings' => [['from' => 'NAME', 'to' => 'TITLE']]
        ]), $base, $source);
        $sheet = $this->sheet($base);
        $sheet->getSorters()->addFromString('NAME', 'DESC');
        $mapped = $mapper->map($sheet, false);

        $this->assertSame('TITLE', $mapped->getSorters()->getFirst()->getAttributeAlias());
        $this->assertSame('DESC', $mapped->getSorters()->getFirst()->getDirection());
        $this->assertSame('NAME', $sheet->getSorters()->getFirst()->getAttributeAlias());
        $this->assertTrue($mapped->isEmpty());
    }

    /**
     * Empty sources must not manufacture a row for a constant.
     *
     * @return void
     */
    public function testEmptyUnionWithConstantDoesNotCreateRows() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['UID']);
        $this->behavior($base, [$this->entry($source, ['UID' => 'UID', "'Empty'" => 'NAME'])]);
        $this->source($source, []);
        $sheet = $this->sheet($base);

        $this->assertSame(0, $sheet->dataRead());
        $this->assertTrue($sheet->isEmpty());
        $this->assertSame(0, $sheet->countRowsInDataSource());
    }

    /**
     * Exercise the native query path, not just replacement-read event handlers.
     *
     * @return void
     */
    public function testUnionsTwoRealCsvDataSources() : void
    {
        $base = $this->csvObject("b,B\nd,D\nf,F\n");
        $source = $this->csvObject("a,A\nc,C\ne,E\n");
        $this->behavior($base, [$this->entry($source)]);
        $sheet = $this->sheet($base);
        $sheet->getSorters()->addFromString('NAME');
        $sheet->getFilters()->addConditionFromString('NAME', 'B,C,D,E,F', '[');
        $sheet->setRowsLimit(2)->setRowsOffset(1);

        $this->assertSame(2, $sheet->dataRead());
        $this->assertSame(['C', 'D'], $sheet->getColumnValues('NAME'));
        $this->assertSame(5, $sheet->countRowsInDataSource());
        $this->assertTrue($sheet->isPaged());
    }

    /**
     * Hidden sorting columns must be mapped even when not explicitly selected.
     *
     * @return void
     */
    public function testAddsMissingSorterColumnAndKeepsDuplicateUids() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['UID', 'NAME']);
        $this->behavior($base, [$this->entry($source)]);
        $this->source($source, [['UID' => 'same', 'NAME' => 'B'], ['UID' => 'same', 'NAME' => 'A']]);
        $sheet = DataSheetFactory::createFromObject($base);
        $sheet->getColumns()->addFromExpression('UID');
        $sheet->getSorters()->addFromString('NAME');
        $sheet->setRowsLimit(1);

        $this->assertSame(1, $sheet->dataRead());
        $this->assertSame(['A'], $sheet->getColumnValues('NAME'));
        $this->assertTrue($sheet->getColumns()->getByExpression('NAME')->getHidden());
        $sheet->setRowsLimit(0);
        $this->assertSame(2, $sheet->dataRead());
        $this->assertSame(['same', 'same'], $sheet->getColumnValues('UID'));
    }

    /**
     * A constant-only projection must retain every matching source row.
     *
     * @return void
     */
    public function testConstantOnlyProjectionPreservesCardinality() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['UID']);
        $this->behavior($base, [$this->entry($source, ["'Orders'" => 'NAME'])]);
        $this->source($source, [['UID' => '1'], ['UID' => '2']]);
        $sheet = DataSheetFactory::createFromObject($base);
        $sheet->getColumns()->addFromExpression('NAME');
        $sheet->setAutoSort(false);

        $this->assertSame(2, $sheet->dataRead());
        $this->assertSame(['Orders', 'Orders'], $sheet->getColumnValues('NAME'));
    }

    /**
     * Related source expressions must be rejected rather than read implicitly.
     *
     * @return void
     */
    public function testRejectsRelatedSourceColumns() : void
    {
        $base = $this->object(['UID', 'NAME']);
        $source = $this->object(['UID', 'NAME']);
        $related = $this->object(['UID', 'NAME']);
        $source->getAttribute('UID')->setRelationFlag(true);
        $source->addRelation(new Relation(
            $this->workbench,
            RelationCardinalityDataType::N_TO_ONE($this->workbench),
            $source->getAttribute('UID')->getId(),
            'UID',
            '',
            $source,
            $source->getAttribute('UID'),
            $related->getId()
        ));
        $behavior = $this->behavior($base, [$this->entry($source, ['UID' => 'UID', 'UID__NAME' => 'NAME'])]);

        $this->expectException(BehaviorConfigurationError::class);
        $this->expectExceptionMessage('related data');
        $behavior->getUnionMappings()[0]->createUnionSheet($this->sheet($base));
    }

    /**
     * Build a real local CSV object with a separate file connector.
     *
     * @param string $contents
     * @return MetaObjectInterface
     */
    private function csvObject(string $contents) : MetaObjectInterface
    {
        $path = tempnam(sys_get_temp_dir(), 'union-');
        $this->temporaryFiles[] = $path;
        file_put_contents($path, $contents);
        $connection = DataConnectionFactory::createFromPrototype($this->workbench, FileContentsConnector::class, new UxonObject([
            'base_path' => sys_get_temp_dir(),
            'error_if_file_not_found' => true
        ]));
        $object = MetaObjectFactory::createTemporary($this->workbench, 'Union CSV fixture', $path, CsvBuilder::class, $connection);
        foreach (['UID', 'NAME'] as $index => $alias) {
            $attribute = MetaObjectFactory::addAttributeTemporary($object, $alias, $alias, (string) $index, DataTypeFactory::createFromPrototype($this->workbench, StringDataType::class));
            $attribute->setFilterable(true);
            $attribute->setSortable(true);
        }
        $object->setUidAttributeAlias('UID');
        return $object;
    }

    /**
     * Build cached temporary objects with real datatypes and no database.
     *
     * @param string[] $aliases
     * @param bool $readable
     * @return MetaObjectInterface
     */
    private function object(array $aliases, bool $readable = false) : MetaObjectInterface
    {
        $connection = DataConnectionFactory::createFromPrototype($this->workbench, TransparentConnector::class);
        $object = MetaObjectFactory::createTemporary($this->workbench, 'Union fixture', '', DummyQueryBuilder::class, $connection, $readable);
        foreach ($aliases as $alias) {
            MetaObjectFactory::addAttributeTemporary($object, $alias, $alias, $alias, DataTypeFactory::createFromPrototype($this->workbench, StringDataType::class));
        }
        $object->setUidAttributeAlias($aliases[0]);
        return $object;
    }

    /**
     * @param MetaObjectInterface $base
     * @param array[] $entries
     * @return UnionBehavior
     */
    private function behavior(MetaObjectInterface $base, array $entries) : UnionBehavior
    {
        $behavior = BehaviorFactory::createFromUxon($base, UnionBehavior::class, new UxonObject(['union_objects' => $entries]));
        $behavior->register();
        $this->behaviors[] = $behavior;
        return $behavior;
    }

    /**
     * @param MetaObjectInterface $source
     * @param array<string,string> $columns
     * @return array
     */
    private function entry(MetaObjectInterface $source, array $columns = ['UID' => 'UID', 'NAME' => 'NAME']) : array
    {
        $mappings = [];
        foreach ($columns as $from => $to) {
            $mappings[] = ['from' => $from, 'to' => $to];
        }
        return ['object_alias' => $source->getAliasWithNamespace(), 'union_to_base_mapper' => ['column_to_column_mappings' => $mappings]];
    }

    /**
     * @param MetaObjectInterface $object
     * @return DataSheetInterface
     */
    private function sheet(MetaObjectInterface $object) : DataSheetInterface
    {
        $sheet = DataSheetFactory::createFromObject($object);
        $sheet->getColumns()->addFromExpression('UID');
        $sheet->getColumns()->addFromExpression('NAME');
        $sheet->setAutoSort(false);
        return $sheet;
    }

    /**
     * Supply source data while keeping real filtering, sorting and event dispatch.
     *
     * @param MetaObjectInterface $object
     * @param array[] $rows
     * @return void
     */
    private function source(MetaObjectInterface $object, array $rows) : void
    {
        $this->workbench->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), function(OnBeforeReadDataEvent $event) use ($object, $rows) {
            if ($event->isPreventRead() || ! $event->getDataSheet()->getMetaObject()->isExactly($object)) {
                return;
            }
            $all = DataSheetFactory::createFromObject($object);
            foreach ($object->getAttributes() as $attribute) {
                $all->getColumns()->addFromExpression($attribute->getAlias());
            }
            $all->addRows($rows);
            $all = $all->extract($event->getDataSheet()->getFilters());
            $all->getSorters()->importUxonObject($event->getDataSheet()->getSorters()->exportUxonObject());
            $all->setAutoSort(false)->sort();
            $total = $all->countRows();
            $limited = array_slice($all->getRows(), $event->getOffset(), $event->getLimit() > 0 ? $event->getLimit() : null);
            $event->getDataSheet()->removeRows()->addRows($limited);
            $event->getDataSheet()->setCounterForRowsInDataSource($total);
            $event->preventRead(true);
            $this->workbench->eventManager()->dispatch(new OnReadDataEvent(
                $event->getDataSheet(),
                $event->getLimit(),
                $event->getOffset(),
                $event->getDataSheet()->countRows()
            ));
        }, -PHP_INT_MAX);
    }
}