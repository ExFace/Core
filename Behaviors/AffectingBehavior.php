<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\DataSheet\AbstractDataSheetEvent;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeReplaceDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnReplaceDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;

/**
 * ALPHA! Do not use yet!
 * 
 * Makes a child-object affect its parent whenever it is changed
 * 
 * For example, if we have an `ORDER` object and multiple `ORDER_POS` for that order. Changing the `QTY`
 * of an order position obviously also changes the order. If the order has a TimeStampingBehavior, we
 * expect the last-change time to update when the quantity of a position is changed.
 * 
 * Technically this means an `OnUpdateData` event of order position is also an `OnUpdateData` for the
 * order. That easy for updates, but less straight-forward for creates or deletes: when a position is created,
 * the order is updated.
 * 
 * ## Examples
 * 
 * ### Order positions change their order
 * 
 * The behavior is to be attached to `ORDER_POS`. Assuming this object has a relation called `ORDER`, the behavior
 * configuration would look like this:
 * 
 * ```
 * 
 * {
 *	    "changes_affect_relations": [
 *		    ORDER
 *	    ]
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 * 
 */
class AffectingBehavior extends AbstractBehavior
{
    private array $eventMapping = [];
    
    private array $eventMappingModifications = [];
    
    private array $registeredEventListeners = [];
    
    private $inProgress = false;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $eventMapping = !empty($this->eventMapping) ? $this->eventMapping : $this->getDefaultEventMapping();
        $eventMapping = array_merge($eventMapping, $this->eventMappingModifications);
        $this->validateEventMapping($eventMapping);
        
        if (! empty($eventMapping)) {
            $eventMgr = $this->getWorkbench()->eventManager();
            foreach ($eventMapping as $fromEvent => $toEvent) {
                $eventMgr->addListener($fromEvent, [$this, 'onEventRelay'], $this->getPriority());
                $this->registeredEventListeners[$fromEvent] = $toEvent;
            }
        }

        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $eventMgr = $this->getWorkbench()->eventManager();
        if (! empty($this->registeredEventListeners)) {
            foreach ($this->registeredEventListeners as $fromEvent => $toEvent) {
                $eventMgr->removeListener($fromEvent, [$this, 'onEventRelay']);
            }
        }
        
        $this->registeredEventListeners = [];
        return $this;
    }

    /**
     * Checks the passed array for invalid event names and throws an error if any
     * invalid event names are detected. Both keys and values will be checked!
     * 
     * @param array $eventMapping
     * @return void
     */
    protected function validateEventMapping(array $eventMapping) : void
    {
        $invalidEvents = [];
        foreach ($eventMapping as $from => $to) {
            if(!is_a($from, AbstractDataSheetEvent::class, true)) {
                $invalidEvents[] = $from;
            }
            
            if(!is_a($to, AbstractDataSheetEvent::class, true)) {
                $invalidEvents[] = $to;
            }
        }
        
        if(!empty($invalidEvents)) {
            $invalidEvents = array_unique($invalidEvents);
            $msg = "The following event names could not be matched with a corresponding class:".PHP_EOL.PHP_EOL;
            foreach ($invalidEvents as $index => $eventName) {
                $msg .= ($index + 1).': '.$eventName.PHP_EOL;
            }
            
            throw new BehaviorConfigurationError($this, $msg);
        }
    }

    /**
     * Executes the action if applicable
     * 
     * @param EventInterface $event
     * @return void
     */
    public function onEventRelay(AbstractDataSheetEvent $event) : void
    {
        if ($this->isDisabled()) {
            return;
        }
        
        $inputSheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $inputSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
		
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        
        $logbook->addDataSheet('Input data', $inputSheet);
        $logbook->addLine('Reacting to event `' . $event::getEventName() . '`');
        $logbook->addLine('Found input data for object ' . $inputSheet->getMetaObject()->__toString());
        $logbook->setIndentActive(1);
        
        $eventManager = $this->getWorkbench()->eventManager();
        $eventManager->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));

        // Ignore loops
        if ($this->inProgress === true) {
            $logbook->addLine('**Skipped** because of operation performed from within the same behavior (e.g. reading missing data)');
            $eventManager->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }

        // Ignore empty sheets - nothing to calculate here!
        if ($inputSheet->isEmpty()) {
            return;
        }

        $this->inProgress = true;
        foreach ($this->getAffectedRelationPaths() as $relationString) {
            $relPath = RelationPathFactory::createFromString($this->getObject(), $relationString);
            $targetObject = $relPath->getEndObject();
            // TODO trigger event for target object. We need to create a DataSheet that will have the UIDs
            // of the target object instances. E.g. for ORDER_POS original UpdateData we need to find all
            // UIDs of ORDERs (read the ORDER column of the original event data) and create a DataSheet
            // for the object ORDER, give it the UID
            $targetSheet = DataSheetFactory::createFromObject($targetObject);
            $targetSheet->getColumns()->addFromSystemAttributes();
            // TODO check if inputSheet has the required columns! May need to read them first. But this
            // will only work if we have a UID column
            $targetUids = $inputSheet->getColumns()->getByExpression($relationString)->getValues();

            // Read the target data first?
            $targetSheet->getFilters()->addConditionFromValueArray($targetSheet->getUidColumn()->getExpressionObj(), $targetUids);
            $targetSheet->dataRead();

            // Now we have an empty data sheet with just the UID column filled.
            // TODO trigger the event
            $eventType = $this->eventMapping[get_class($event)];
            if($eventType === null) {
                throw new BehaviorConfigurationError($this, "Could not relay update to ".$targetObject.", because no valid event mapping was found for ".get_class($event)."!");
            }
            
            $eventManager->dispatch(new $eventType($event->getDataSheet(), $event->getTransaction()));
        }
        $this->inProgress = false;

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    /**
     * Relations to the objects, that will be affected by changes on this one
     * 
     * For example, for an `ORDER_POSITION` object
     * 
     * - `ORDER`
     * - `ORDER__CUSTOMER__CUSTOMER_STATS`
     * 
     * @uxon-property changes_affect_relations
     * @uxon-type metamodel:relation[]
     * @uxon-template [""]
     * 
     * @return \exface\Core\Behaviors\AffectingBehavior
     */
    public function setChangesAffectRelations(UxonObject $arrayOfRelationPaths) : AffectingBehavior
    {
        $this->relations = $arrayOfRelationPaths->toArray();

        /* IDEA mark related attributes as system to check ORDER timestamp when saving ORDER_POS.
         * Will probably not work yet because system attributes are only direct ones.
         * Do this LATER!
        foreach ($this->relations as $relationString) {
            $relPath = RelationPathFactory::createFromString($this->getObject(), $relationString);
            $relObj = $relPath->getEndObject();
            foreach ($relObj->getAttributes()->getSystem() as $systemAttr) {
                // If ORDER has system attribute MODIFIED_ON, give ORDER_POS the system attibute ORDER__MODIFIED_ON
                $this->getObject()->getAttribute(RelationPath::relationPathAdd($relationString, $systemAttr->getAlias()))->setSystem();
            }
            $this->getObject()->getRelation($relKey)->getRightObject();

        }
        */
        
        return $this;
    }


    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO
        return $uxon;
    }

    /**
     * 
     * @return string[]
     */
    protected function getAffectedRelationPaths() : array
    {
        return $this->relations;
    }

    /**
     * NOTE: This property is a stub and has no effect!
     * Overwrite all entries in the default event mapping with a custom event mapping.
     *
     * Since setting this property disables all events not explicitly defined in the
     * new mapping, it is especially useful if you want to exclude certain events. For example:
     *
     * ```
     *
     * "overwrite_event_mapping" :
     * {
     *      "OnCreateDataEvent" : "OnUpdateDataEvent"
     * }
     *
     * ```
     *
     * will only trigger on `OnCreateDataEvent` and will relay that trigger to the `OnUpdateDataEvent` of
     * all affected relations.
     *
     * @uxon-property overwrite_event_mapping
     * @uxon-type object
     * @uxon-template {"OnCreateDataEvent":"OnUpdateDataEvent","OnBeforeCreateDataEvent":"OnBeforeCreateDataEvent","OnUpdateDataEvent":"OnUpdateDataEvent","OnBeforeUpdateDataEvent":"OnBeforeCreateDataEvent","OnReplaceDataEvent":"OnUpdateDataEvent","OnBeforeReplaceDataEvent":"OnBeforeUpdateDataEvent","OnDeleteDataEvent":"OnUpdateDataEvent","OnBeforeDeleteDataEvent":"OnBeforeCreateDataEvent"}
     *
     * @param UxonObject $eventMapping
     * @return $this
     */
    public function setOverwriteEventMapping(UxonObject $eventMapping) : static
    {
        // TODO: Implement actual event mapping.
        //$this->eventMapping = $eventMapping->toArray();
        return $this;
    }

    /**
     * NOTE: This property is a stub and has no effect!
     * Modify the current event mapping with custom event mappings.
     *
     * Any default mappings not explicitly mentioned in this property will remain unchanged.
     * Existing keys will be overwritten, while new keys will be appended. For example:
     *
     * ```
     *
     * "modify_event_mapping" :
     * {
     *      "OnCreateDataEvent" : "OnCreateDataEvent", // This overwrites the default mapping.
     *      "MyOwnDataEvent" : "OnCreateDataEvent" // This appends a new mapping.
     * }
     *
     * ```
     *
     * @uxon-property modify_event_mapping
     * @uxon-type object
     * @uxon-template {"OnCreateDataEvent":"OnUpdateDataEvent","OnBeforeCreateDataEvent":"OnBeforeCreateDataEvent","OnUpdateDataEvent":"OnUpdateDataEvent","OnBeforeUpdateDataEvent":"OnBeforeCreateDataEvent","OnReplaceDataEvent":"OnUpdateDataEvent","OnBeforeReplaceDataEvent":"OnBeforeUpdateDataEvent","OnDeleteDataEvent":"OnUpdateDataEvent","OnBeforeDeleteDataEvent":"OnBeforeCreateDataEvent"}
     *
     * @param UxonObject $eventMappingModifications
     * @return $this
     */
    public function setModifyEventMapping(UxonObject $eventMappingModifications) : static
    {
        // TODO: Implement actual event mapping.
        //$this->eventMappingModifications = $eventMappingModifications->toArray();
        return $this;
    }

    /**
     * @return string[]
     */
    protected function getDefaultEventMapping() : array
    {
        return [
            OnCreateDataEvent::class => OnUpdateDataEvent::class,
            OnBeforeCreateDataEvent::class => OnBeforeCreateDataEvent::class,
            OnUpdateDataEvent::class => OnUpdateDataEvent::class,
            OnBeforeUpdateDataEvent::class => OnBeforeCreateDataEvent::class,
            OnReplaceDataEvent::class => OnUpdateDataEvent::class,
            OnBeforeReplaceDataEvent::class => OnBeforeUpdateDataEvent::class,
            OnDeleteDataEvent::class => OnUpdateDataEvent::class,
            OnBeforeDeleteDataEvent::class => OnBeforeCreateDataEvent::class
        ];
    }
}