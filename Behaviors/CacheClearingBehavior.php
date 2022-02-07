<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Interfaces\Events\DataSheetEventInterface;

/**
 * This behavior clears the workbench cache every time data of the object is
 * saved, updated or deleted.
 * 
 * @author Andrej Kabachnik
 *
 */
class CacheClearingBehavior extends AbstractBehavior
{    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $handler = array(
            $this,
            'handleEvent'
        );
        $this->getWorkbench()->eventManager()->addListener(OnUpdateDataEvent::getEventName(), $handler);
        $this->getWorkbench()->eventManager()->addListener(OnCreateDataEvent::getEventName(), $handler);
        $this->getWorkbench()->eventManager()->addListener(OnDeleteDataEvent::getEventName(), $handler);
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $handler = array(
            $this,
            'handleEvent'
        );
        $this->getWorkbench()->eventManager()->removeListener(OnUpdateDataEvent::getEventName(), $handler);
        $this->getWorkbench()->eventManager()->removeListener(OnCreateDataEvent::getEventName(), $handler);
        $this->getWorkbench()->eventManager()->removeListener(OnDeleteDataEvent::getEventName(), $handler);
        
        return $this;
    }
    
    /**
     * 
     * @param DataSheetEventInterface $event
     */
    public function handleEvent(DataSheetEventInterface $event)
    {
        if ($event->getDataSheet()->getMetaObject()->is($this->getObject())) {
            $event->getWorkbench()->getCache()->clear();
        }
    }

}