<?php
namespace exface\Core\Tests\PHPUnit\Unit\Actions;

use exface\Core\Actions\SendToWidget;
use exface\Core\CommonLogic\Tasks\ResultData;
use exface\Core\CommonLogic\Tasks\ResultMessage;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Tasks\ResultDataInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use PHPUnit\Framework\TestCase;

/**
 * ⚠️ ANTI-PATTERN — DO NOT IMITATE. Kept as a teaching example of how NOT to test.
 *
 * This class "tests" the inherited, final {@see \exface\Core\CommonLogic\AbstractAction::handle()}
 * by mocking `WorkbenchInterface` and freezing the internal call structure of `handle()`.
 * That directly violates Core Rule 2 of the testing policy:
 *
 *   "A unit test must be writable without mocking Workbench. If a scenario cannot be
 *    covered without mocking `Workbench`, `WorkbenchInterface` or a chain of Workbench
 *    services, do not write it as a unit test."
 *
 * See `.github/instructions/testing.instructions.md` → sections "Core Rules" and
 * "Cover Changes Without Mocking Workbench".
 *
 * Why this is a bad test (all three failures the policy describes show up below):
 *   (#2) Brittle: the `onlyMethods([...])` list hard-codes `handle()`'s current internal
 *        call structure. Any refactor of `handle()` breaks the mock build or lets an
 *        un-stubbed method reach a real Workbench call — a failure on a non-bug.
 *   (#3) Blind at the seam that matters: the bare `WorkbenchInterface` mock turns the
 *        event manager, logger, transaction and logbook into silent no-ops, baking in a
 *        guess that action events and transaction autocommit never affect the result.
 *        If that guess is wrong the test stays green while production diverges.
 *   (#1) Tautological (weaker here): it only reaches green because the Workbench
 *        collaborators were stubbed into no-ops, so a pass proves consistency with the
 *        stubs rather than correct end-to-end behavior.
 *
 * The genuinely unit-testable behavior of `SendToWidget` is the `send_value` pointer
 * parsing (`parseSendValuePointer()` and friends), which needs no Workbench at all.
 * End-to-end coverage of `handle()` belongs in application-level/Behat tests
 * (`axenox/bdt` or the owning app). Real tests should take the name `SendToWidgetTest`.
 *
 * The whole class is skipped in {@see setUp()} so it never runs and never interferes
 * with the real suite.
 */
class SendToWidgetHandleAntiPatternTest extends TestCase
{
    /**
     * Skips every scenario in this class so the anti-pattern cannot run or interfere.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->markTestSkipped(
            'Intentional anti-pattern kept for documentation only. It mocks Workbench, '
            . 'which violates Core Rule 2 of .github/instructions/testing.instructions.md. '
            . 'Do not re-enable; write real tests in SendToWidgetTest instead.'
        );
    }

    /**
     * BAD EXAMPLE: asserts input pass-through, but only by neutralising every Workbench seam.
     *
     * @return void
     */
    public function testHandleReturnsInputDataAsDataResult(): void
    {
        $object = $this->createMock(MetaObjectInterface::class);
        $object->method('__toString')->willReturn('my.App.OBJECT');

        $inputData = $this->createMock(DataSheetInterface::class);
        $inputData->method('countRows')->willReturn(2);
        $inputData->method('getMetaObject')->willReturn($object);

        $task = $this->createTask(true, $inputData);
        $action = $this->createAction();

        $result = $action->handle($task, $this->createMock(DataTransactionInterface::class));

        $this->assertInstanceOf(ResultDataInterface::class, $result);
        $this->assertInstanceOf(ResultData::class, $result);
        $this->assertSame($inputData, $result->getData());
        $this->assertSame($task, $result->getTask());
    }

    /**
     * BAD EXAMPLE: asserts the empty-message branch, again only because the seams are inert.
     *
     * @return void
     */
    public function testHandleWithoutInputDataReturnsEmptyMessageResult(): void
    {
        $task = $this->createTask(false, null);
        $action = $this->createAction();

        $result = $action->handle($task, $this->createMock(DataTransactionInterface::class));

        $this->assertInstanceOf(ResultMessage::class, $result);
        $this->assertNotInstanceOf(ResultData::class, $result);
        $this->assertSame('', $result->getMessage());
        $this->assertSame($task, $result->getTask());
    }

    /**
     * FLAW (#2 brittle, #3 blind): mocks Workbench and freezes handle()'s internals.
     *
     * The `onlyMethods([...])` list below is a snapshot of `handle()`'s current call
     * structure (logbook, workbench, applicability check, after-hook). It must be kept
     * in sync with private implementation details — the opposite of testing the public
     * contract. The bare `WorkbenchInterface` mock makes event dispatch, logging and the
     * transaction do nothing, hiding any behavior that really lives at those seams.
     *
     * @return SendToWidget
     */
    private function createAction(): SendToWidget
    {
        // FLAW (#3): a wide god-object mocked as an inert double.
        $workbench = $this->createMock(WorkbenchInterface::class);
        // FLAW (#2): this list hard-codes how handle() calls its collaborators.
        $action = $this->getMockBuilder(SendToWidget::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench', 'getLogBook', 'validateApplicability', 'performAfter'])
            ->getMock();
        $action->method('getWorkbench')->willReturn($workbench);
        $action->method('performAfter')->willReturn($action);
        return $action;
    }

    /**
     * FLAW (#3 blind): the task's Workbench is mocked too, so ResultFactory's internal
     * `$task->getWorkbench()` call resolves against assumptions instead of reality.
     *
     * @param bool $hasInputData
     * @param DataSheetInterface|null $inputData
     * @return TaskInterface
     */
    private function createTask(bool $hasInputData, ?DataSheetInterface $inputData): TaskInterface
    {
        $workbench = $this->createMock(WorkbenchInterface::class);
        $task = $this->createMock(TaskInterface::class);
        $task->method('getWorkbench')->willReturn($workbench);
        $task->method('hasInputData')->willReturn($hasInputData);
        if ($inputData !== null) {
            $task->method('getInputData')->willReturn($inputData);
        }
        return $task;
    }
}
