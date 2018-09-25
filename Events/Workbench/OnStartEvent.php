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
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public function getWorkbench()
    {
        return $this->workbench;
    }
}