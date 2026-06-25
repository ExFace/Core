<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\DataTypes\DateDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\TaskFactory;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Task run by the scheduler with additional control options for timeouts, etc.
 * 
 * Scheduler tasks have more options to control how they are run:
 * - `time_to_check` - how often to check, if the task is still running
 * - `timeout` - when is a task to be considered as timed out
 * 
 * If you need to run another type of task in the scheduler, you define it in detail
 * under `task_to_run`. There you can instantiate any task class.
 * 
 * @author Andrej Kabachnik
 *
 */
class ScheduledTask extends GenericTask
{
    public const DEFAULT_TIMEOUT = '6 hours';
    public const DEFAULT_MAX_TIMEOUT = '1 day';
    
    private $schedulerUid = null;
    private ?UxonObject $innerTaskUxon = null;
    private ?\DateInterval $timeOutInterval = null;
    private ?\DateInterval $maxTimeOutInterval = null;
    private ?string $timeToCheckRaw = null;
    private ?string $timeoutRaw = null;

    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon = null, string $schedulerUid = null)
    {
        parent::__construct($workbench);
        $this->schedulerUid = $schedulerUid;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * @return string
     */
    public function getSchedulerUid() : string
    {
        return $this->schedulerUid;
    }
    
    /**
     * Returns the raw `time_to_check` value as configured, or null if unset.
     *
     * Needed for serialisation: the interval getter casts to \DateInterval and
     * falls back to a default, so it cannot reproduce the original UXON value.
     *
     * @return string|null
     */
    public function getTimeToCheck() : ?string
    {
        return $this->timeToCheckRaw;
    }

    /**
     * Returns the raw `timeout` value as configured, or null if unset.
     *
     * Needed for serialisation - see getTimeToCheck() for the rationale.
     *
     * @return string|null
     */
    public function getTimeout() : ?string
    {
        return $this->timeoutRaw;
    }

    /**
     * Time to check if the task is still running - to mark the queue item as timeouted if not.
     * 
     * If a task is marked as running for longer than this interval, the system will check if the process is still
     * alive, and if it isn't, mark it as timed out to make room for a new run.
     * 
     * Make sure, this interval is smaller, than the total `timeout`, but larger, than the running interval of
     * the scheduler.
     * 
     * Use the common PHP-DateInterval syntax:
     * - Supports `year(s)`, `month(s)`, `week(s)`, `day(s)`, `hour(s)`, `minute(s)`.
     * - Concatenate with `+`.
     * - For example: `1 day`, `4 hours + 30 minutes`, `1 Week + 2 Days`.
     * 
     * @uxon-property time_to_check
     * @uxon-type string
     * 
     * @param string $timeout
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function setTimeToCheck(string $timeout) : ScheduledTask
    {
        try {
            $this->timeToCheckRaw = $timeout;
            $this->timeOutInterval = DateDataType::castInterval($timeout);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid value "' . $timeout . '" for `time_to_check` configuration', null, $e);
        }
        
        return $this;
    }

    /**
     * @return \DateInterval
     * @throws \InvalidArgumentException
     */
    public function getTimeToCheckInterval() : \DateInterval
    {
        // Initialize with the default timeout interval.
        if($this->timeOutInterval === null) {
            $this->setTimeToCheck(self::DEFAULT_TIMEOUT);
        }

        return $this->timeOutInterval;
    }

    /**
     * Time to mark the queue item as timeouted even if the process is still running.
     * 
     * If a task is marked as running for longer than this interval, it will be marked as timed out, regardless of whether
     * the process is still alive. Set this value conservatively to avoid unintentional parallel execution.
     *
     * Use the common PHP-DateInterval syntax:
     * - Supports `year(s)`, `month(s)`, `week(s)`, `day(s)`, `hour(s)`, `minute(s)`.
     * - Concatenate with `+`.
     * - For example: `1 day`, `4 hours + 30 minutes`, `1 Week + 2 Days`, but also as number of seconds (`600` for 10 min).
     *
     * @uxon-property timeout
     * @uxon-type string
     * @uxon-default 1 day
     *
     * @param string|int $timeout
     * @return $this
     * @throws \Exception
     */
    protected function setTimeout(string|int $timeout) : ScheduledTask
    {
        try {
            $this->timeoutRaw = (string) $timeout;
            $this->maxTimeOutInterval = DateDataType::castInterval($timeout);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid value "' . $timeout . '" for `timeout` configuration', null, $e);
        }

        return $this;
    }

    /**
     * @return \DateInterval
     * @throws \Exception
     */
    public function getTimeoutInterval() : \DateInterval
    {
        if($this->maxTimeOutInterval === null) {
            $this->setTimeout(self::DEFAULT_MAX_TIMEOUT);
        }
        
        return $this->maxTimeOutInterval;
    }

    /**
     * @return TaskInterface
     */
    public function getTaskToRun() : TaskInterface
    {
        if ($this->innerTaskUxon === null) {
            return $this;
        } else {
            return TaskFactory::createFromUxon($this->getWorkbench(), $this->innerTaskUxon);
        }
    }

    /**
     * The task to be run when the scheduled task is due.
     * 
     * Here you can add advanced configuration to the task you actually want the scheduler
     * to run - even if the scheduled task itself does not have these options.
     * 
     * ```
     * {
     *      "class": "\exface\Core\CommonLogic\Tasks\CliScriptTask",
     *      "commands": ["php -v"],
     *      "command_timeout": 10000 
     * }
     * 
     * ```
     * @uxon-property task_to_run
     * @uxon-type \exface\Core\CommonLogic\Tasks\GenericTask
     * @uxon-template {"class": "\exface\Core\CommonLogic\Tasks\GenericTask"}
     * 
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setTaskToRun(UxonObject $uxon) : ScheduledTask
    {
        $this->innerTaskUxon = $uxon;
        return $this;
    }

    /**
     * Export this scheduled task to UXON.
     *
     * GenericTask::exportUxonObject() does not know about the scheduler-specific
     * properties, so without this override task_to_run, time_to_check and timeout
     * would be lost on export. task_to_run is the critical one: it carries the
     * inner task definition (e.g. a CliScriptTask) including its own "class", so
     * losing it means the scheduled work can never be reconstructed from the queue.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('class', '\\' . get_class($this));

        // getTaskToRun() returns $this when no inner task was defined; comparing
        // against $this both skips the export and prevents infinite recursion.
        $innerTask = $this->getTaskToRun();
        if ($innerTask !== $this) {
            $uxon->setProperty('task_to_run', $innerTask->exportUxonObject());
        }
        if ($this->getTimeToCheck() !== null) {
            $uxon->setProperty('time_to_check', $this->getTimeToCheck());
        }
        if ($this->getTimeout() !== null) {
            $uxon->setProperty('timeout', $this->getTimeout());
        }
        return $uxon;
    }
}