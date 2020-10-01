<?php
namespace exface\Core\Events\Workbench;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Event fired after the workbench has been started.
 * 
 * @event exface.Core.Workbench.OnStart
 *
 * @author Andrej Kabachnik
 *        
 */
class OnStartEvent extends AbstractEvent
{
    private $workbench = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\WorkbenchInterface
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    
    public static function getEventName() : string
    {
        return 'exface.Core.Workbench.OnStart';
    }
}