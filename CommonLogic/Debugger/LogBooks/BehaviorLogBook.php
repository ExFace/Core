<?php
namespace exface\Core\CommonLogic\Debugger\LogBooks;

use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;

class BehaviorLogBook extends DataLogBook
{
    private $event = null;
    
    private $behavior = null;

    /**
     * 
     * @param string $title
     * @param BehaviorInterface $behavior
     * @param EventInterface $event
     */
    public function __construct(string $title, BehaviorInterface $behavior, EventInterface $event = null)
    {
        parent::__construct($title);
        $this->event = $event;
        $this->behavior = $behavior;
        $this->addSection($behavior->getName());
        $this->addLine(PhpClassDataType::findClassNameWithoutNamespace($behavior) . ' of ' . $behavior->getObject()->__toString());
        if ($event !== null) {
            $eventObj = $this->getObjectOfEvent($event);
            $this->addLine('Reacting to event `' . $event::getEventName() . '`' . ($eventObj !== null ? ' for object ' . $eventObj->__toString() : ''));
        }
    }
    
    /**
     * 
     * @return BehaviorInterface
     */
    public function getBehavior() : BehaviorInterface
    {
        return $this->behavior;
    }
    
    /**
     * 
     * @return EventInterface|NULL
     */
    public function getEvent() : ?EventInterface
    {
        return $this->event;
    }
    
    protected function getObjectOfEvent(EventInterface $event) : ?MetaObjectInterface
    {
        switch (true) {
            case $event instanceof MetaObjectEventInterface:
                return $event->getObject();
            case $event instanceof DataSheetEventInterface:
                return $event->getDataSheet()->getMetaObject();
        }
        return null;
    }
}