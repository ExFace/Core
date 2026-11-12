<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\Behaviors\UnionBehavior;
use exface\Core\CommonLogic\ContextManager;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataConnectors\TransparentConnector;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\QueryBuilders\DummyQueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Verifies shared union logging with real lifecycle events and no database.
 */
class UnionBehaviorSharedLogbookTest extends TestCase
{
    private Workbench $workbench;
    private DataSheetInterface $sheet;

    /** @var UnionBehavior[] */
    private array $participants = [];

    /**
     * {@inheritDoc}
     * @see TestCase::setUp()
     */
    protected function setUp() : void
    {
        $this->workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'union-shared-logbook-test']);
        $this->workbench->getConfig()->loadConfigFile(dirname(__DIR__, 3) . '\\Config\\exface.Core.config.json');
        $model = new ReflectionProperty(Workbench::class, 'mm');
        $model->setAccessible(true);
        $model->setValue($this->workbench, new Model($this->workbench));
        $context = new ReflectionProperty(Workbench::class, 'context');
        $context->setAccessible(true);
        $context->setValue($this->workbench, new ContextManager($this->workbench));

        $connection = DataConnectionFactory::createFromPrototype($this->workbench, TransparentConnector::class);
        $base = MetaObjectFactory::createTemporary($this->workbench, 'Union logbook fixture', '', DummyQueryBuilder::class, $connection, false);
        MetaObjectFactory::addAttributeTemporary($base, 'UID', 'UID', 'UID', DataTypeFactory::createFromPrototype($this->workbench, StringDataType::class));
        $base->setUidAttributeAlias('UID');
        foreach (['First participant', 'Second participant'] as $name) {
            $behavior = BehaviorFactory::createFromUxon($base, UnionBehavior::class, new UxonObject([
                'name' => $name,
                'union_objects' => []
            ]));
            $this->participants[] = $behavior;
            $behavior->register();
        }
        $this->sheet = DataSheetFactory::createFromObject($base);
        $this->sheet->getColumns()->addFromExpression('UID');
        $this->sheet->setAutoSort(false);
    }

    /**
     * {@inheritDoc}
     * @see TestCase::tearDown()
     */
    protected function tearDown() : void
    {
        foreach ($this->participants as $participant) {
            $participant->disable();
        }
        MetaObjectFactory::clearCache(null, true, true);
    }

    /**
     * Every participant keeps its events but appends to the same logbook.
     *
     * @return void
     */
    public function testSharesOneLogbookAcrossParticipantLifecycleEvents() : void
    {
        $before = [];
        $after = [];
        $this->workbench->eventManager()->addListener(OnBeforeBehaviorAppliedEvent::getEventName(), function(OnBeforeBehaviorAppliedEvent $event) use (&$before) {
            $before[] = $event;
            $event->getLogbook()->addLine('Starting ' . $event->getBehavior()->getName());
        });
        $this->workbench->eventManager()->addListener(OnBehaviorAppliedEvent::getEventName(), function(OnBehaviorAppliedEvent $event) use (&$after) {
            $after[] = $event;
            $event->getLogbook()->addLine('Finished ' . $event->getBehavior()->getName());
        });

        $this->assertSame(0, $this->sheet->dataRead());
        $this->assertCount(2, $before);
        $this->assertCount(2, $after);
        $logbook = $before[0]->getLogbook();
        foreach ($this->participants as $index => $participant) {
            $this->assertSame($participant, $before[$index]->getBehavior());
            $this->assertSame($participant, $after[$index]->getBehavior());
            $this->assertSame($logbook, $before[$index]->getLogbook());
            $this->assertSame($logbook, $after[$index]->getLogbook());
            $this->assertStringContainsString('Starting ' . $participant->getName(), (string) $logbook);
            $this->assertStringContainsString('Finished ' . $participant->getName(), (string) $logbook);
        }
    }

    /**
     * Errors from a later participant retain earlier and failing participant lines.
     *
     * @return void
     */
    public function testErrorRetainsAllParticipantHistory() : void
    {
        $logbooks = [];
        $this->workbench->eventManager()->addListener(OnBeforeBehaviorAppliedEvent::getEventName(), function(OnBeforeBehaviorAppliedEvent $event) use (&$logbooks) {
            $logbooks[] = $event->getLogbook();
            $event->getLogbook()->addLine('Starting ' . $event->getBehavior()->getName());
            if ($event->getBehavior() === $this->participants[1]) {
                throw new \RuntimeException('Second participant failed.');
            }
        });

        try {
            $this->sheet->dataRead();
            $this->fail('Expected the participant error to propagate.');
        } catch (BehaviorRuntimeError $error) {
            $this->assertStringContainsString('Second participant failed.', $error->getMessage());
            $this->assertCount(2, $logbooks);
            $this->assertSame($logbooks[0], $error->getLogbook());
            $this->assertSame($logbooks[1], $error->getLogbook());
            foreach ($this->participants as $participant) {
                $this->assertStringContainsString('Starting ' . $participant->getName(), (string) $error->getLogbook());
                $this->assertFalse($participant->isInProgress());
            }
        }
    }
}
