<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\Behaviors\UnionBehavior;
use exface\Core\CommonLogic\ContextManager;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataConnectors\TransparentConnector;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\QueryBuilders\DummyQueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Verifies live sheet references in separate union logbooks using a database-free Workbench.
 */
class UnionBehaviorDataLogbookTest extends TestCase
{
    /**
     * Each behavior logs the combined event sheet and numbers only its own sources.
     *
     * @return void
     */
    public function testLogsEventSheetAndOwnNumberedUnionSheets() : void
    {
        $this->checkLoggedSheets(true);
    }

    /**
     * A virtual base's logged event sheet also contains the combined union rows.
     *
     * @return void
     */
    public function testLogsCombinedEventSheetForVirtualObject() : void
    {
        $this->checkLoggedSheets(false);
    }

    /**
     * Exercise real union reads with deterministic rows supplied at the source boundary.
     *
     * @param bool $readableBase
     * @return void
     */
    private function checkLoggedSheets(bool $readableBase) : void
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'union-data-logbook-test']);
        $workbench->getConfig()->loadConfigFile(dirname(__DIR__, 3) . '\\Config\\exface.Core.config.json');
        $model = new ReflectionProperty(Workbench::class, 'mm');
        $model->setAccessible(true);
        $model->setValue($workbench, new Model($workbench));
        $context = new ReflectionProperty(Workbench::class, 'context');
        $context->setAccessible(true);
        $context->setValue($workbench, new ContextManager($workbench));
        $behaviors = [];
        try {
            $objects = [];
            foreach (['base', 'first', 'second', 'third'] as $name) {
                $connection = DataConnectionFactory::createFromPrototype($workbench, TransparentConnector::class);
                $object = MetaObjectFactory::createTemporary(
                    $workbench, $name, '', DummyQueryBuilder::class, $connection, $name !== 'base' || $readableBase
                );
                MetaObjectFactory::addAttributeTemporary(
                    $object, 'UID', 'UID', 'UID', DataTypeFactory::createFromPrototype($workbench, StringDataType::class)
                );
                $object->setUidAttributeAlias('UID');
                $objects[$name] = $object;
                $workbench->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), function(OnBeforeReadDataEvent $event) use ($object, $name) {
                    if ($event->isPreventRead() || ! $event->getDataSheet()->getMetaObject()->isExactly($object)) {
                        return;
                    }
                    $event->getDataSheet()->addRows([['UID' => $name]], false, false);
                    $event->getDataSheet()->setCounterForRowsInDataSource(1);
                    $event->preventRead(true);
                }, -PHP_INT_MAX);
            }
            foreach ([['first', 'second'], ['third']] as $sources) {
                $entries = [];
                foreach ($sources as $name) {
                    $entries[] = [
                        'object_alias' => $objects[$name]->getAliasWithNamespace(),
                        'union_to_base_mapper' => [
                            'column_to_column_mappings' => [['from' => 'UID', 'to' => 'UID']]
                        ]
                    ];
                }
                $behavior = BehaviorFactory::createFromUxon($objects['base'], UnionBehavior::class, new UxonObject([
                    'union_objects' => $entries
                ]));
                $behaviors[] = $behavior;
                $behavior->register();
            }
            $logbooks = [];
            $workbench->eventManager()->addListener(OnBehaviorAppliedEvent::getEventName(), function(OnBehaviorAppliedEvent $event) use (&$logbooks) {
                if ($event->getBehavior() instanceof UnionBehavior) {
                    $logbooks[] = $event->getLogbook();
                }
            });
            $sheet = DataSheetFactory::createFromObject($objects['base']);
            $sheet->getColumns()->addFromExpression('UID');
            $sheet->setAutoSort(false);

            $this->assertSame($readableBase ? 4 : 3, $sheet->dataRead());
            $this->assertCount(2, $logbooks);
            $this->assertNotSame($logbooks[0], $logbooks[1]);
            $firstSheets = $logbooks[0]->getDataSheets();
            $secondSheets = $logbooks[1]->getDataSheets();
            $this->assertSame(['Base data', 'Union 1', 'Union 2'], array_keys($firstSheets));
            $this->assertSame(['Base data', 'Union 1'], array_keys($secondSheets));
            foreach ([$firstSheets, $secondSheets] as $loggedSheets) {
                $this->assertSame($sheet, $loggedSheets['Base data']);
                $this->assertSame(
                    $readableBase ? ['base', 'first', 'second', 'third'] : ['first', 'second', 'third'],
                    $loggedSheets['Base data']->getColumnValues('UID')
                );
            }
            $this->assertSame(['first'], $firstSheets['Union 1']->getColumnValues('UID'));
            $this->assertSame(['second'], $firstSheets['Union 2']->getColumnValues('UID'));
            $this->assertSame(['third'], $secondSheets['Union 1']->getColumnValues('UID'));
        } finally {
            foreach ($behaviors as $behavior) {
                $behavior->disable();
            }
            MetaObjectFactory::clearCache(null, true, true);
        }
    }
}
