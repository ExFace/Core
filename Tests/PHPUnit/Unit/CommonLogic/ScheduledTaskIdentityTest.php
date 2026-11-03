<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\Tasks\ScheduledTask;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\TaskFactory;
use PHPUnit\Framework\TestCase;

/**
 * Protects the exported task identity used by queue overlap detection.
 */
class ScheduledTaskIdentityTest extends TestCase
{
    /**
     * Keeps a default check interval from making subsequent scheduled runs look different.
     * 
     * @return void
     */
    public function testDefaultCheckIntervalPreservesExport() : void
    {
        $task = new ScheduledTask(new Workbench());
        $before = $task->exportUxonObject()->toJson();
        $reference = new \DateTimeImmutable('2000-01-01 00:00:00');

        $this->assertSame('2000-01-01 06:00:00', $reference->add($task->getTimeToCheckInterval())->format('Y-m-d H:i:s'));
        $this->assertSame($before, $task->exportUxonObject()->toJson());
    }

    /**
     * Reproduces interval resolution between the queue's identity lookup and task insertion.
     * 
     * @return void
     */
    public function testResolvingBothDefaultsMatchesFreshTaskExport() : void
    {
        $workbench = new Workbench();
        $task = new ScheduledTask($workbench);
        $freshTask = new ScheduledTask($workbench);
        $before = $task->exportUxonObject()->toJson();
        $reference = new \DateTimeImmutable('2000-01-01 00:00:00');

        $this->assertSame('2000-01-02 00:00:00', $reference->add($task->getTimeoutInterval())->format('Y-m-d H:i:s'));
        $this->assertSame($before, $task->exportUxonObject()->toJson());
        $task->getTimeToCheckInterval();
        $task->getTimeoutInterval();
        $this->assertSame($freshTask->exportUxonObject()->toJson(), $task->exportUxonObject()->toJson());
    }

    /**
     * Preserves explicit timeout configuration as well as its effective intervals.
     * 
     * @return void
     */
    public function testConfiguredIntervalsSurviveResolutionAndRoundTrip() : void
    {
        $workbench = new Workbench();
        $task = new ScheduledTask($workbench, new UxonObject([
            'time_to_check' => '30 minutes',
            'timeout' => 7200
        ]));
        $before = $task->exportUxonObject()->toJson();
        $reference = new \DateTimeImmutable('2000-01-01 00:00:00');

        $this->assertSame('2000-01-01 00:30:00', $reference->add($task->getTimeToCheckInterval())->format('Y-m-d H:i:s'));
        $this->assertSame('2000-01-01 02:00:00', $reference->add($task->getTimeoutInterval())->format('Y-m-d H:i:s'));
        $this->assertSame($before, $task->exportUxonObject()->toJson());

        /**
         * @var ScheduledTask $restored
         */
        $restored = TaskFactory::createFromUxon($workbench, $task->exportUxonObject());
        $this->assertEquals($task->getTimeToCheckInterval(), $restored->getTimeToCheckInterval());
        $this->assertEquals($task->getTimeoutInterval(), $restored->getTimeoutInterval());
        $this->assertSame($before, $restored->exportUxonObject()->toJson());
    }
}