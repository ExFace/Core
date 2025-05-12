<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Events\CrudPerformedEventInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * A dedicated class for counting CRUD (Create, Read, Update, Delete) operations for a specified set of
 * objects, within a specified scope.
 * 
 * To start counting, simply call `start([$objects], $reset)`. Make sure to call `stop()` before leaving
 * the scope within which you called `start()` or the counter will continue receiving events and counting their results.
 * 
 * @see CrudPerformedEventInterface
 */
class CrudCounter implements WorkbenchDependantInterface
{
    public const COUNT_WRITES = 'writes';
    public const COUNT_CREATES = 'creates';
    public const COUNT_READS = 'reads';
    public const COUNT_UPDATES = 'updates';
    public const COUNT_DELETES = 'deletes';
    
    private int $trackingDepth = -1;
    private array $listeners = [];
    private array $objects = [];
    private array $currentDepth = [];

    private WorkbenchInterface $workbench;
    protected ?int $writes = null;
    protected ?int $creates = null;
    protected ?int $reads = null;
    protected ?int $updates = null;
    protected ?int $deletes = null;

    /**
     * @param WorkbenchInterface $workbench
     * @param int                $trackingDepth
     * CRUD operations can cascade into other CRUD operations
     */
    public function __construct(WorkbenchInterface $workbench, int $trackingDepth = -1)
    {
        $this->workbench = $workbench;
        $this->trackingDepth = $trackingDepth;
    }

    /**
     * Starts counting all CRUD (Create, Read, Update, Delete) operations for all specified objects
     * until `stop()` is called.
     * 
     * You can add additional objects to track at any time with `addObject($object)`.
     * 
     * @param array $objects
     * The objects for which you would like to count. Any events that do not relate to one of these
     * objects will be ignored.
     * @param bool  $reset
     * If TRUE all counters and objects will be reset. If you want to continue your last count, set this value to
     *     FALSE. 
     * @return $this
     * 
     * @see CrudCounter::stop()
     * @see CrudPerformedEventInterface
     */
    public function start(array $objects, bool $reset = true) : CrudCounter
    {
        if($reset) {
            $this->writes = null;
            $this->creates = null;
            $this->reads = null;
            $this->updates = null;
            $this->deletes = null;
            
            $this->objects = [];
        }

        foreach ($objects as $object) {
            $this->addObject($object);
        }

        $this->addListener(OnBeforeCreateDataEvent::getEventName(), 'crudStarted');
        $this->addListener(OnBeforeReadDataEvent::getEventName(), 'crudStarted');
        $this->addListener(OnBeforeUpdateDataEvent::getEventName(), 'crudStarted');
        $this->addListener(OnBeforeDeleteDataEvent::getEventName(), 'crudStarted');

        $this->addListener(OnCreateDataEvent::getEventName(), 'createPerformed');
        $this->addListener(OnReadDataEvent::getEventName(), 'readPerformed');
        $this->addListener(OnUpdateDataEvent::getEventName(), 'updatePerformed');
        $this->addListener(OnDeleteDataEvent::getEventName(), 'deletePerformed');
        
        return $this;
    }

    /**
     * Stop counting.
     * 
     * @return $this
     */
    public function stop() : CrudCounter
    {
        foreach ($this->listeners as $event => $listener) {
            $this->removeListener($event);
        }
        
        foreach ($this->currentDepth as $key => $data) {
            $this->currentDepth[$key] = 0;
        }
        
        return $this;
    }

    /**
     * @param string $eventName
     * @param string $functionName
     * @return void
     */
    protected function addListener(string $eventName, string $functionName) : void
    {
        if(key_exists($eventName, $this->listeners)) {
            return;
        }

        $listener = [$this, $functionName];
        $this->getWorkbench()->eventManager()->addListener(
            $eventName,
            $listener
        );

        $this->listeners[$eventName] = $listener;
    }

    /**
     * @param string $eventName
     * @return void
     */
    protected function removeListener(string $eventName) : void
    {
        if(!key_exists($eventName, $this->listeners)) {
            return;
        }

        $listener = $this->listeners[$eventName];
        $this->getWorkbench()->eventManager()->removeListener(
            $eventName,
            $listener
        );

        unset($this->listeners[$eventName]);
    }

    /**
     * Add another object to be tracked. 
     * 
     * The object will not be added a second time if it is already being tracked.
     * 
     * @param MetaObjectInterface $object
     * @return $this
     */
    public function addObject(MetaObjectInterface $object) : CrudCounter
    {
        $objectAlias = $object->getAliasWithNamespace();
        
        if(key_exists($objectAlias, $this->objects)) {
            return $this;
        }
        
        $this->objects[$objectAlias] = $object;
        $this->currentDepth[$objectAlias] = 0;
        
        return $this;
    }

    /**
     * Remove an object from being tracked.
     * 
     * @param MetaObjectInterface $object
     * @return $this
     */
    public function removeObject(MetaObjectInterface $object) : CrudCounter
    {
        $objectAlias = $object->getAliasWithNamespace();
        
        unset($this->objects[$objectAlias]);
        $this->currentDepth[$objectAlias] = 0;
        
        return $this;
    }

    /**
     * Event hook.
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function crudStarted(DataSheetEventInterface $event) : void
    {
        $object = $event->getDataSheet()->getMetaObject();
        if(!$this->appliesToObject($object)) {
            return;
        }
        
        $objectAlias = $object->getAliasWithNamespace();
        
        if(key_exists($objectAlias, $this->currentDepth)) {
            $this->currentDepth[$objectAlias] += 1;
        } else {
            $this->currentDepth[$objectAlias] = 1;
        }
    }
    
    protected function finalizeOperation(MetaObjectInterface $object, DataSheetEventInterface $event) : bool
    {
        $objectAlias = $object->getAliasWithNamespace();
        
        $inRange = $this->inTrackingRange($objectAlias);
        $this->currentDepth[$objectAlias] -= 1;

        return $inRange;
    }
    
    /**
     * Event hook.
     * 
     * @param CrudPerformedEventInterface $event
     * @return void
     * 
     * @see OnCreateDataEvent
     */
    public function createPerformed(CrudPerformedEventInterface $event) : void
    {
        $object = $event->getDataSheet()->getMetaObject();
        if(!$this->appliesToObject($object)) {
            return;
        }
        
        if(!$this->finalizeOperation($object, $event)) {
            return;
        }
        
        $this->addValueToCounter($event->getAffectedRowsCount(), self::COUNT_CREATES);
        $this->addValueToCounter($event->getAffectedRowsCount(), self::COUNT_WRITES);
    }

    /**
     * Event hook.
     *
     * @param CrudPerformedEventInterface $event
     * @return void
     *
     * @see OnReadDataEvent
     */
    public function readPerformed(CrudPerformedEventInterface $event) : void
    {
        $object = $event->getDataSheet()->getMetaObject();
        if(!$this->appliesToObject($object)) {
            return;
        }

        if(!$this->finalizeOperation($object, $event)) {
            return;
        }

        $this->addValueToCounter($event->getAffectedRowsCount(), self::COUNT_READS);
    }

    /**
     * Event hook.
     *
     * @param CrudPerformedEventInterface $event
     * @return void
     *
     * @see OnUpdateDataEvent
     */
    public function updatePerformed(CrudPerformedEventInterface $event) : void
    {
        $object = $event->getDataSheet()->getMetaObject();
        if(!$this->appliesToObject($object)) {
            return;
        }

        if(!$this->finalizeOperation($object, $event)) {
            return;
        }

        $this->addValueToCounter($event->getAffectedRowsCount(), self::COUNT_UPDATES);
        $this->addValueToCounter($event->getAffectedRowsCount(), self::COUNT_WRITES);
    }

    /**
     * Event hook.
     *
     * @param CrudPerformedEventInterface $event
     * @return void
     *
     * @see OnDeleteDataEvent
     */
    public function deletePerformed(CrudPerformedEventInterface $event) : void
    {
        $object = $event->getDataSheet()->getMetaObject();
        if(!$this->appliesToObject($object)) {
            return;
        }

        if(!$this->finalizeOperation($object, $event)) {
            return;
        }

        $this->addValueToCounter($event->getAffectedRowsCount(), self::COUNT_DELETES);
    }

    /**
     * Check, whether this counter applies to a given object.
     *
     * @param MetaObjectInterface $objectToCheck
     * @return bool
     */
    public function appliesToObject(MetaObjectInterface $objectToCheck) : bool
    {
        foreach ($this->objects as $object) {
            if($object->isExactly($objectToCheck)) {
                return true;
            }
        }

        return false;
    }

    public function inTrackingRange(string $objectAlias) : bool
    {
        if($this->trackingDepth < 0) {
            return true;
        }

        return ($this->currentDepth[$objectAlias] ?? 0) <= $this->trackingDepth;
    }
    
    /**
     * Add a value to one of the tracked counters.
     * 
     * @param int|null $value
     * @param string   $counter
     * The counter you wish to modify. Use one of the `COUNT_` constants, such as `COUNT_WRITES`
     * @return void
     */
    public function addValueToCounter(?int $value, string $counter) : void
    {
        if($value === null || !property_exists($this, $counter)) {
            return;
        }
        
        if($this->{$counter} === null) {
            $this->{$counter} = $value;
        } else {
            $this->{$counter} += $value;
        }
    }

    /**
     * Get the number of WRITES since the last reset.
     * 
     * Returns NULL if the number is unknown.
     * 
     * @return int|null
     */
    public function getWrites() : ?int
    {
        return $this->writes;
    }

    /**
     * Get the number of CREATES since the last reset.
     *
     * Returns NULL if the number is unknown.
     *
     * @return int|null
     */
    public function getCreates() : ?int
    {
        return $this->creates;
    }

    /**
     * Get the number of READS since the last reset.
     *
     * Returns NULL if the number is unknown.
     *
     * @return int|null
     */
    public function getReads() : ?int
    {
        return $this->reads;
    }

    /**
     * Get the number of UPDATES since the last reset.
     *
     * Returns NULL if the number is unknown.
     *
     * @return int|null
     */
    public function getUpdates() : ?int
    {
        return $this->updates;
    }

    /**
     * Get the number of DELETES since the last reset.
     *
     * Returns NULL if the number is unknown.
     *
     * @return int|null
     */
    public function getDeletes() : ?int
    {
        return $this->deletes;
    }

    /**
     * @inheritdoc 
     * @see WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }
}