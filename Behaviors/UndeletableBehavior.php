<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;

/**
 * Prevents the deletion of data if it matches the provided conditions.
 * 
 * ## Examples
 * 
 * ### Prevent deletion of record with ID = 123
 * 
 * ```
 * {
 *  "condition_group": {
 *      "operator": "AND",
 *      "conditions": [
 *          {
 *              "expression": "ID",
 *              "comparator": "==",
 *              "value": 123
 *          }
 *      ]
 *  }
 * }
 * 
 * ```
 * 
 * ### Prevent deletion of records with STATUS >= 90, but no 95
 * 
 * ```
 * {
 *  "condition_group": {
 *      "operator": "AND",
 *      "conditions": [
 *          {
 *              "expression": "STATUS",
 *              "comparator": ">=",
 *              "value": 90
 *          },{
 *              "expression": "STATUS",
 *              "comparator": "!==",
 *              "value": 95
 *          }
 *      ]
 *  }
 * }
 * 
 * ```
 * 
 * ### Prevent deletion of customers with at least one order
 * 
 * In this case, the behavior should be attached to the `CUSTOMER` object. It will automatically
 * read the number of the customer's orders every time a customer is about to be deleted an compare
 * it to `0`.
 * 
 * ```
 * {
 *  "condition_group": {
 *      "operator": "AND",
 *      "conditions": [
 *          {
 *              "expression": "ORDER__ID:COUNT",
 *              "comparator": "!==",
 *              "value": 0
 *          }
 *      ]
 *  }
 * }
 * 
 * ```
 * 
 * ### Only allow deletion of entries added today
 * 
 * ```
 * {
 *  "condition_group": {
 *      "operator": "AND",
 *      "conditions": [
 *          {
 *              "expression": "CREATED_ON",
 *              "comparator": "!==",
 *              "value": 0
 *          }
 *      ]
 *  }
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class UndeletableBehavior extends AbstractBehavior
{
    private $conditionGroupUxon = null;
    
    private $conditionGroup = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'handleOnBeforeDelete']);
        
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * 
     * @param OnBeforeDeleteDataEvent $event
     */
    public function handleOnBeforeDelete(OnBeforeDeleteDataEvent $event)
    {
        // TODO add column to input data if not exists
        // TODO read data if $eventData->isFresh() === false and $eventData()->getMetaObject()->isReadable()
        // TODO $this->getConditionGroup()->evaluate()
    }
    
    /**
     * Prevent deletion if the data matches this condition group.
     * 
     * @uxon-property condition_group
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroupInterface
     * @uxon-template {"operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}
     * 
     * @param UxonObject $uxon
     * @return UndeletableBehavior
     */
    public function setConditionGroup(UxonObject $uxon) : UndeletableBehavior
    {
        $this->conditionGroupUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * @return ConditionGroupInterface
     */
    protected function getConditionGroup() : ConditionGroupInterface
    {
        if ($this->conditionGroup === null && $this->conditionGroupUxon !== null) {
            $this->conditionGroup = ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->conditionGroupUxon, $this->getObject());
        }
        return $this->conditionGroup;
    }
}