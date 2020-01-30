<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Behaviors\DataSheetDeleteForbiddenError;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Exceptions\RuntimeException;
use Symfony\Component\Translation\Catalogue\OperationInterface;

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
        if ($this->isDisabled())
            return;
        
        // TODO add column to input data if not exists
        $eventDataSheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $eventDataSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $dataSheet = $eventDataSheet->copy();
        
        foreach ($this->getConditionGroup()->getConditions() as $condition){
            $expression = $condition->getExpression();
            switch (true){
                case $expression->isMetaAttribute():
                    try {
                        $attribute = $dataSheet->getMetaObject()->getAttribute($condition->getAttributeAlias());
                    } catch (\Exception $e) {
                        continue;
                    }
                    if (! $dataSheet->getColumns()->getByAttribute($attribute)){
                        $dataSheet->getColumns()->addFromAttribute($attribute);
                    }
                    break;
                case $expression->isString():
                case $expression->isFormula():
                    $dataSheet->getColumns()->addFromExpression($expression);
            }
       }
        
        $labelAttributeAlias = $dataSheet->getMetaObject()->getLabelAttributeAlias();
        if ($labelAttributeAlias !== null){
            $dataSheet->getColumns()->addFromAttribute($dataSheet->getMetaObject()->getLabelAttribute());
        }
        
        // TODO read data if $eventData->isFresh() === false and $eventData()->getMetaObject()->isReadable()
        if ($dataSheet->isFresh() === false && $dataSheet->getMetaObject()->isReadable()){
            $uidCol = $dataSheet->getUidColumn();
            if ($uidCol === false){
                $uidCol = $dataSheet->getColumns()->addFromUidAttribute();
            }
            $dataSheet->getFilters()->addConditionFromColumnValues($uidCol);
            
            $dataSheet->dataRead();
        }
        
        // TODO $this->getConditionGroup()->evaluate()
        $conditionGroup =  $this->getConditionGroup();
        $result = false;
        $errorCondition = null;
        $operator = $conditionGroup->getOperator();
        foreach($dataSheet->getRows() as $idx => $row){
            
            $resRow = array();
            $resSingleRow = null;
            foreach ($conditionGroup->getConditions() as $con){
                $resSingleColumn = $con->evaluate($dataSheet, $idx);
                switch (true){
                    case $operator == EXF_LOGICAL_AND && $resSingleColumn === false:
                        $resSingleRow = false;
                        break;
                    case $operator == EXF_LOGICAL_OR && $resSingleColumn === true:
                        $resSingleRow = true;
                        $errorCondition = $con;
                        break;
                    case $operator == EXF_LOGICAL_AND && $resSingleColumn === true:
                        $errorCondition = $con;
                    default:
                        $resRow[] = $resSingleColumn;
                }
                if ($resSingleRow !== null){
                    break;
                }
            }
            
            if ($resSingleRow === null){
                switch ($operator){
                    case EXF_LOGICAL_AND:
                        $resSingleRow = (in_array(false, $resRow) === true);
                        break;
                    case EXF_LOGICAL_OR:
                        $resSingleRow = in_array(true, $resRow);
                        break;
                    case EXF_LOGICAL_XOR:
                        $resSingleRow = count(array_filter($resRow, function(bool $val){return $val === true;})) === 1;
                        break;
                    default:
                        throw new RuntimeException('Unsupported logical operator "' . $operator . '" in condition group "' . $conditionGroup->toString() . '"!');
                }
            }
            
            if ($resSingleRow === true){
                $result = true;
                break;
            }
        }

        if ($result === true){
            $errorRowDescriptor = '';
                        
            if ($labelAttributeAlias !== null && $row[$labelAttributeAlias] !== null){
                $errorRowDescriptor = '"' . $row[$labelAttributeAlias] . '"'; 
            }
            if ($errorRowDescriptor == ''){
                $errorRowDescriptor = $idx;
            }
            
            throw new DataSheetDeleteForbiddenError($dataSheet, 'Delete Exeption: Item ' . $errorRowDescriptor . ' in the current selection of ' . $dataSheet->getMetaObject()->getAlias() . ' does fulfill the condition ' . $errorCondition->toString() . ' set in a behaviour, its deletion is therefore prohibited.');
        }
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