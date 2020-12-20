<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Stopwatch\Stopwatch;
use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * The profiler can be used to stop the time for things like actions, data queries, etc.
 * 
 * For example, to stop the time for an action call `->start($action)` and `->stop($action)`
 * - the latter will give you the time between these two calls in milliseconds for this
 * specific action instance.
 * 
 * @author Andrej Kabachnik
 *
 */
class Profiler implements WorkbenchDependantInterface
{
    private $stopwatch = null;

    private $startOffsetMs = 0;
    
    private $workbench = null;
    
    private $lapIds = [];

    /**
     * 
     * @param Workbench $workbench
     * @param int $startOffsetMs
     */
    public function __construct(Workbench $workbench, int $startOffsetMs = 0)
    {
        $this->workbench = $workbench;
        $this->reset($startOffsetMs);
    }

    /**
     * Resets the internal stopwatch
     * 
     * @param int $startOffsetMs
     * @return Profiler
     */
    public function reset(int $startOffsetMs = 0) : Profiler
    {
        $this->startOffsetMs = $startOffsetMs;
        $this->stopwatch = new Stopwatch();
        $this->stopwatch->start('TOTAL');
        return $this;
    }
    
    /**
     * Starts the time for the given object and returns a generated lap id
     * 
     * @param mixed $subject
     * @return int
     */
    public function start($subject) : int
    {
        $lapId = $this->getLapId($subject);
        $this->stopwatch->start($lapId);
        return $lapId;
    }
    
    /**
     * Stops the time for the given object and returns it's duration
     * 
     * @param mixed $subject
     * @return float|NULL
     */
    public function stop($subject) : ?float
    {
        $lapId = $this->getLapId($subject);
        return $this->stopId($lapId);
    }
    
    /**
     * Stops the time for the given lap id and return it's duration
     * 
     * @param int $lapId
     * @return float|NULL
     */
    public function stopId(int $lapId) : ?float
    {
        if ($this->stopwatch->isStarted($lapId)) {
            $this->stopwatch->stop($lapId);
            return $this->stopwatch->getEvent($lapId)->getDuration();
        }
        return null;
    }
    
    /**
     * Stops the profiler completely and returns the total duration
     * 
     * @return float
     */
    public function stopTotal() : float
    {
        $this->stopwatch->stop('TOTAL');
        return $this->getDurationTotal();
    }
    
    /**
     * Returns the duration of a given object in milliseconds or NULL if no lap was started for it.
     * 
     * @param mixed $subject
     * @return float|NULL
     */
    public function getDuration($subject) : ?float
    {
        if (! in_array($subject, $this->lapIds)) {
            return null;
        }
        return $this->getDurationById($this->getLapId($subject));
    }
    
    /**
     * Returns the total duration in milliseconds
     * 
     * @return float
     */
    public function getDurationTotal() : float
    {
        return $this->stopwatch->getEvent('TOTAL')->getDuration();
    }
    
    /**
     * Returns the duration of a give lap id in milliseconds
     * @param int $lapId
     * @return float|NULL
     */
    public function getDurationById(int $lapId) : ?float
    {
        try {
            return $this->stopwatch->getEvent($lapId)->getDuration();
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * @param mixed $subject
     * @return int
     */
    protected function getLapId($subject) : int
    {
        $lapId = array_search($subject, $this->lapIds);
        if ($lapId === false) {
            $this->lapIds[] = $subject;
            $lapId = count($this->lapIds) - 1;
        }
        return $lapId;
    }
}
?>