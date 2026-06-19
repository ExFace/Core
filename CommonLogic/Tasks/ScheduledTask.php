<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class ScheduledTask extends GenericTask
{
    public const DEFAULT_TIMEOUT = '6 hours';
    public const DEFAULT_MAX_TIMEOUT = '1 day';
    
    private $schedulerUid = null;
    private ?\DateInterval $timeOutInterval = null;
    private ?\DateInterval $maxTimeOutInterval = null;

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
     * If a task is marked as running for longer than this interval, the system will check if the process is still
     *  alive, and if it isn't, mark it as timed out to make room for a new run.
     * 
     * Use the common PHP-DateInterval syntax:
     * - Supports `year(s)`, `month(s)`, `week(s)`, `day(s)`, `hour(s)`, `minute(s)`.
     * - Concatenate with `+`.
     * - For example, `1 day`, `4 hours + 30 minutes`, `1 Week + 2 Days`.
     * 
     * @uxon-property queue_timeout
     * @uxon-type string
     * 
     * @param string $timeout
     * @return $this
     * @throws \Exception
     */
    protected function setQueueTimeOut(string $timeout) : ScheduledTask
    {
        try {
            $this->timeOutInterval = \DateInterval::createFromDateString($timeout);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid value "' . $timeout . '" for `queue_timeout` configuration', null, $e);
        }
        
        return $this;
    }

    /**
     * @return \DateInterval
     * @throws \Exception
     */
    public function getQueueTimeOutInterval() : \DateInterval
    {
        // Initialize with the default timeout interval.
        if($this->timeOutInterval === null) {
            $this->setQueueTimeOut(self::DEFAULT_TIMEOUT);
        }

        return $this->timeOutInterval;
    }

    /**
     * If a task is marked as running for longer than this interval, it will be marked as timed out, regardless of whether
     *  the process is still alive. Set this value conservatively to avoid unintentional parallel execution.
     *
     * Use the common PHP-DateInterval syntax:
     * - Supports `year(s)`, `month(s)`, `week(s)`, `day(s)`, `hour(s)`, `minute(s)`.
     * - Concatenate with `+`.
     * - For example, `1 day`, `4 hours + 30 minutes`, `1 Week + 2 Days`.
     *
     * @uxon-property queue_timeout_max
     * @uxon-type string
     *
     * @param string $timeout
     * @return $this
     * @throws \Exception
     */
    protected function setMaxQueueTimeOut(string $timeout) : ScheduledTask
    {
        try {
            $this->maxTimeOutInterval = \DateInterval::createFromDateString($timeout);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid value "' . $timeout . '" for `queue_timeout_max` configuration', null, $e);
        }

        return $this;
    }

    public function getMaxQueueTimeOutInterval() : \DateInterval
    {
        if($this->maxTimeOutInterval === null) {
            $this->setMaxQueueTimeOut(self::DEFAULT_MAX_TIMEOUT);
        }
        
        return $this->maxTimeOutInterval;
    }
}