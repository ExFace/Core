<?php
namespace exface\Core\Tests\PHPUnit\Unit\Log;

use exface\Core\CommonLogic\EventManager;
use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\CommonLogic\Log\Processors\ActionAliasProcessor;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Events\Action\OnActionFailedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Exercises action lifecycle tracking with a real dispatcher and no Workbench startup or context.
 */
class ActionAliasProcessorTest extends TestCase
{
    private EventManager $events;
    private ActionAliasProcessor $processor;

    /**
     * Initializes only event dispatch; no persisted event configuration or metamodel is loaded.
     *
     * {@inheritDoc}
     * @see TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'action-alias-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $this->events = (new \ReflectionClass(EventManager::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(EventManager::class, 'dispatcher'))->setValue($this->events, new EventDispatcher());
        (new \ReflectionProperty(Workbench::class, 'event_manager'))->setValue($workbench, $this->events);
        $this->processor = new ActionAliasProcessor($workbench);
    }

    /**
     * Nested actions replace the parent during execution and restore it upon successful completion.
     */
    public function testNestedActionsAndLifecycleListenerPriorities(): void
    {
        self::assertSame('', $this->currentAlias());
        $parent = $this->startAction('test.Parent');
        self::assertSame('test.Parent', $this->currentAlias());

        $this->events->addListener(OnBeforeActionPerformedEvent::getEventName(), function (): void {
            self::assertSame('test.Child', $this->currentAlias());
        });
        $child = $this->startAction('test.Child');
        $childRecord = ($this->processor)(new LogRecord(new \DateTimeImmutable(), 'Test', Level::Debug, 'Child'));
        self::assertSame('test.Child', $this->currentAlias());

        $this->events->addListener(OnActionPerformedEvent::getEventName(), function (OnActionPerformedEvent $event): void {
            self::assertSame($event->getAction()->getAliasWithNamespace(), $this->currentAlias());
        });
        $this->finishAction($child);
        self::assertSame('test.Parent', $this->currentAlias());
        $this->finishAction($parent);
        self::assertSame('', $this->currentAlias());
        self::assertSame('test.Child', $childRecord->extra[CsvLogConfig::KEY_ACTION_ALIAS]);
    }

    /**
     * A caught child failure restores the parent, and a top-level failure clears the alias.
     */
    public function testFailuresRestoreParentAndClearTopLevelAlias(): void
    {
        $parent = $this->startAction('test.Parent');
        $child = $this->startAction('test.Child');
        $this->events->addListener(OnActionFailedEvent::getEventName(), function (OnActionFailedEvent $event): void {
            self::assertSame($event->getAction()->getAliasWithNamespace(), $this->currentAlias());
        });

        foreach ([$child, $parent] as $action) {
            $this->events->dispatch(new OnActionFailedEvent(
                $action,
                $this->createStub(TaskInterface::class),
                new \RuntimeException('Action failed'),
                $this->createStub(DataTransactionInterface::class),
                static fn() => null
            ));
            self::assertSame($action === $child ? 'test.Parent' : '', $this->currentAlias());
        }
    }

    /**
     * Starts a lifecycle without running action logic or requiring its input sheet.
     *
     * @param string $alias
     * @return ActionInterface
     */
    private function startAction(string $alias): ActionInterface
    {
        $action = $this->createStub(ActionInterface::class);
        $action->method('getAliasWithNamespace')->willReturn($alias);
        $this->events->dispatch(new OnBeforeActionPerformedEvent(
            $action,
            $this->createStub(TaskInterface::class),
            $this->createStub(DataTransactionInterface::class),
            static fn() => null
        ));
        return $action;
    }

    /**
     * Completes an action through the event dispatcher.
     *
     * @param ActionInterface $action
     * @return void
     */
    private function finishAction(ActionInterface $action): void
    {
        $this->events->dispatch(new OnActionPerformedEvent(
            $action,
            $this->createStub(ResultInterface::class),
            $this->createStub(DataTransactionInterface::class),
            static fn() => null
        ));
    }

    /**
     * Reads the public processor output, without any context access.
     *
     * @return string
     */
    private function currentAlias(): string
    {
        $record = ($this->processor)(new LogRecord(new \DateTimeImmutable(), 'Test', Level::Debug, 'Message'));
        return $record->extra[CsvLogConfig::KEY_ACTION_ALIAS];
    }
}
