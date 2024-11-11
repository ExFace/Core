<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Events\DataSheet\OnReadDataEvent;
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

    private $inProgress = false;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        // TODO need all data modifying events from \exface\Core\Events\DataSheet
        // OnCreateData -> OnUpdate
        // OnUpdate -> OnUpdate
        // OnDelete -> OnUpdate
        // OnBeforeCreate -> OnBeforeUpdate
        // OnBeforeUpdate -> OnBeforeUpdate
        // OnBeforeDelete -> OnBeforeUpdate
        $eventMgr = $this->getWorkbench()->eventManager();
        if (! empty($this->calculateOnEventNames)) {
            foreach ($this->calculateOnEventNames as $eventName) {
                $eventMgr->addListener($eventName, [$this, 'onEventRelay'], $this->getPriority());
            }
        } else {
            $eventMgr->addListener(OnReadDataEvent::getEventName(), [$this, 'onEventRelay'], $this->getPriority());
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
        if (! empty($this->calculateOnEventNames)) {
            foreach ($this->calculateOnEventNames as $eventName) {
                $eventMgr->removeListener($eventName, [$this, 'onEventRelay']);
            }
        } else {
            $eventMgr->removeListener(OnReadDataEvent::getEventName(), [$this, 'onEventRelay']);
        }
        
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
     * Executes the action if applicable
     * 
     * @param EventInterface $event
     * @return void
     */
    public function onEventRelay(DataSheetEventInterface $event)
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
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));

        // Ignore loops
        if ($this->inProgress === true) {
            $logbook->addLine('**Skipped** because of operation performed from within the same behavior (e.g. reading missing data)');
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
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
    protected function setChangesAffectRelations(UxonObject $arrayOfRelationPaths) : AffectingBehavior
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
     * @return string[]
     */
    protected function getAffectedRelationPaths() : array
    {
        return $this->relations;
    }
}