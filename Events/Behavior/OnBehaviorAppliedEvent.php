<?php
namespace exface\Core\Events\Behavior;

use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Events\EventInterface;

/**
 * Event fired when after a behavior applied its logic
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBehaviorAppliedEvent extends OnBeforeBehaviorAppliedEvent
{
    private $dataChanged = false;
    
    public function __construct(BehaviorInterface $behavior, EventInterface $processedEvent = null, LogBookInterface $logbook = null, $dataChanged = false)
    {
        parent::__construct($behavior, $processedEvent, $logbook);
        $this->dataChanged = $dataChanged;
    }
    
    /**
     * 
     * @return bool
     */
    public function isDataModified() : bool
    {
        return $this->dataChanged;
    }
}