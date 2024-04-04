<?php
namespace exface\Core\Events\Workbench;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Event fired when workbench cleanup is launched: listeners should cleaning up temporary or expiring data.
 * 
 * Each listerner should use `$event->addResultMessage()` to tell the event, what has bee done (and
 * ultimately its origin).
 * 
 * Specific cleaning areas may be specify by passing an array with area names to the constructor. Every
 * listener should know which area it belongs to and can call `$event->isAreaToBeCleaned()` to find out
 * if the event affects it's area. Area names are case insensitive.
 * 
 * @event exface.Core.Workbench.OnCleanUp
 *
 * @author Andrej Kabachnik
 *        
 */
class OnCleanUpEvent extends AbstractEvent
{
    private $workbench = null;
    
    private $areas = null;
    
    private $resultMessages = [];
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string[] $areas
     */
    public function __construct(WorkbenchInterface $workbench, array $areas = null)
    {
        $this->workbench = $workbench;
        $this->areas = $areas;
    }
    
    public static function getEventName() : string
    {
        return 'exface.Core.Workbench.OnCleanUp';
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
     * @param string $name
     * @return bool
     */
    public function isAreaToBeCleaned(string $name) : bool
    {
        $name = mb_strtolower($name);
        return $this->areas === null || in_array($name, $this->areas);
    }
    
    /**
     * 
     * @param string $whatHasBeenCleaned
     * @return OnCleanUpEvent
     */
    public function addResultMessage(string $whatHasBeenCleaned) : OnCleanUpEvent
    {
        $this->resultMessages[] = mb_strtolower($whatHasBeenCleaned);
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getResultMessages() : array
    {
        return $this->resultMessages;
    }
}