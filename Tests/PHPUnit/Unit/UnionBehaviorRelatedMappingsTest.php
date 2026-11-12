<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\Behaviors\UnionBehavior;
use exface\Core\CommonLogic\ContextManager;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Model\Relation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataConnectors\TransparentConnector;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\QueryBuilders\DummyQueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers inherited relation mappings using a real, database-free Workbench.
 */
class UnionBehaviorRelatedMappingsTest extends TestCase
{
    private Workbench $workbench;
    private UnionBehavior $behavior;
    private MetaObjectInterface $base;
    private MetaObjectInterface $child;

    /**
     * {@inheritDoc}
     * @see TestCase::setUp()
     */
    protected function setUp() : void
    {
        $this->workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'union-related-unit-test']);
        $this->workbench->getConfig()->loadConfigFile(dirname(__DIR__, 3) . '\\Config\\exface.Core.config.json');
        $model = new ReflectionProperty(Workbench::class, 'mm');
        $model->setAccessible(true);
        $model->setValue($this->workbench, new Model($this->workbench));
        $context = new ReflectionProperty(Workbench::class, 'context');
        $context->setAccessible(true);
        $context->setValue($this->workbench, new ContextManager($this->workbench));

        $this->base = $this->object(['UID']);
        $related = $this->object(['UID', 'NAME', 'CATEGORY']);
        $this->base->getAttribute('UID')->setRelationFlag(true);
        $this->base->addRelation(new Relation(
            $this->workbench,
            RelationCardinalityDataType::N_TO_ONE($this->workbench),
            $this->base->getAttribute('UID')->getId(),
            'UID',
            '',
            $this->base,
            $this->base->getAttribute('UID'),
            $related->getId()
        ));
        $this->child = $this->object(['UID']);
        $this->child->extendFromObject($this->base);
        $this->behavior = BehaviorFactory::createFromUxon($this->base, UnionBehavior::class, new UxonObject([
            'union_objects' => [['object_alias' => $this->child->getAliasWithNamespace()]]
        ]));
    }

    /**
     * {@inheritDoc}
     * @see TestCase::tearDown()
     */
    protected function tearDown() : void
    {
        if (isset($this->behavior)) {
            $this->behavior->disable();
        }
        MetaObjectFactory::clearCache(null, true, true);
    }

    /**
     * Related columns, nested filters and sorters retain their inherited aliases.
     *
     * @return void
     */
    public function testMapsRelatedRequestsAndPreservesCustomColumnNames() : void
    {
        $sheet = DataSheetFactory::createFromObject($this->base);
        $sheet->getColumns()->addFromExpression('UID__NAME', 'display_name');
        $sheet->getFilters()->addNestedGroupFromString('OR')
            ->addConditionFromString('UID__CATEGORY', 'Keep', '==')
            ->addConditionFromString('UID__NAME', 'Other', '==');
        $sheet->getSorters()->addFromString('UID__NAME', 'DESC');
        $union = $this->behavior->getUnionMappings()[0];

        $request = $union->createUnionSheet($sheet);
        $this->assertTrue($request->getMetaObject()->isExactly($this->child));
        $this->assertTrue((bool) $request->getColumns()->getByExpression('UID__NAME'));
        $this->assertFalse((bool) $request->getColumns()->getByExpression('UID__CATEGORY'));
        $expected = DataSheetFactory::createFromObject($this->child);
        $expected->getFilters()->addNestedGroupFromString('OR')
            ->addConditionFromString('UID__CATEGORY', 'Keep', '==')
            ->addConditionFromString('UID__NAME', 'Other', '==');
        $this->assertSame(
            $expected->getFilters()->exportUxonObject()->toJson(),
            $request->getFilters()->exportUxonObject()->toJson()
        );
        $this->assertSame('UID__NAME', $request->getSorters()->getFirst()->getAttributeAlias());
        $this->assertSame('DESC', $request->getSorters()->getFirst()->getDirection());

        $request->addRows([['UID__NAME' => 'Zed'], ['UID__NAME' => 'Amy']]);
        $this->assertSame(
            [['display_name' => 'Zed'], ['display_name' => 'Amy']],
            $union->mapToBase($request, $sheet)->getRows()
        );
    }

    /**
     * A related sorter not selected by the caller is read for global pagination.
     *
     * @return void
     */
    public function testReadsHiddenRelatedSorterBeforePagination() : void
    {
        $this->behavior->register();
        $this->workbench->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), function(OnBeforeReadDataEvent $event) {
            if (! $event->getDataSheet()->getMetaObject()->isExactly($this->child)) {
                return;
            }
            $source = $event->getDataSheet();
            $this->assertSame('UID__NAME', $source->getSorters()->getFirst()->getAttributeAlias());
            $source->addRows([
                ['UID' => '2', 'UID__NAME' => 'Amy'],
                ['UID' => '1', 'UID__NAME' => 'Zed']
            ]);
            $source->setCounterForRowsInDataSource(2);
            $event->preventRead(true);
        });
        $sheet = DataSheetFactory::createFromObject($this->base);
        $sheet->getColumns()->addFromExpression('UID');
        $sheet->getSorters()->addFromString('UID__NAME');

        $this->assertSame(1, $sheet->dataRead(1, 1));
        $this->assertSame(['1'], $sheet->getColumnValues('UID'));
        $this->assertSame(['Zed'], $sheet->getColumnValues('UID__NAME'));
        $this->assertTrue($sheet->getColumns()->getByExpression('UID__NAME')->getHidden());
    }

    /**
     * Build cached temporary objects with real datatypes and no database.
     *
     * @param string[] $aliases
     * @return MetaObjectInterface
     */
    private function object(array $aliases) : MetaObjectInterface
    {
        $connection = DataConnectionFactory::createFromPrototype($this->workbench, TransparentConnector::class);
        $object = MetaObjectFactory::createTemporary($this->workbench, 'Union relation fixture', '', DummyQueryBuilder::class, $connection, false);
        foreach ($aliases as $alias) {
            MetaObjectFactory::addAttributeTemporary($object, $alias, $alias, $alias, DataTypeFactory::createFromPrototype($this->workbench, StringDataType::class));
        }
        $object->setUidAttributeAlias('UID');
        return $object;
    }
}
