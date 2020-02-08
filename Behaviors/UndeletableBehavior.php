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
     * This method is responsible for analyzing the expressions for the behavior defined in the metamodel, gethering the object's
     * relevant data from the metamodel and evaluating whether the deletion for a dataset is allowed, or not.
     * 
     * @param OnBeforeDeleteDataEvent $event
     * @throws RuntimeException
     * @throws DataSheetDeleteForbiddenError
     */
    public function handleOnBeforeDelete(OnBeforeDeleteDataEvent $event)
    {
        if ($this->isDisabled())
            return;
        
        $eventDataSheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $eventDataSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        $dataSheet = $eventDataSheet->copy();
        
        // add column to input data if not exists
        foreach ($this->getConditionGroup()->getConditions() as $condition){
            $expression = $condition->getExpression();
            // differenciate between multiple forms of expressions, and handle them differently
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
                    break;
                default:
                    throw new RuntimeException('Unsupported expression "' . $expression->toString() . '" in behavior "' . $this->getAlias() . '"!');
            }
       }
        
        // attach label attribute to datasheet, if exists
        $labelAttributeAlias = $dataSheet->getMetaObject()->getLabelAttributeAlias();
        if ($labelAttributeAlias !== null){
            $dataSheet->getColumns()->addFromAttribute($dataSheet->getMetaObject()->getLabelAttribute());
        }
        
        // read data if $eventData->isFresh() === false and $eventData()->getMetaObject()->isReadable()
        if ($dataSheet->isFresh() === false && $dataSheet->getMetaObject()->isReadable()){
            $uidCol = $dataSheet->getUidColumn();
            if ($uidCol === false){
                $uidCol = $dataSheet->getColumns()->addFromUidAttribute();
            }
            $dataSheet->getFilters()->addConditionFromColumnValues($uidCol);
            
            $dataSheet->dataRead();
        }
        
        $conditionGroup =  $this->getConditionGroup();
        $result = false;
        $errorCondition = null;
        $operator = $conditionGroup->getOperator();
        
        // evaluate the dataset row by row and condition by condition, so that if an item is detected as being
        // undeletable, the exact item and the crucial expression can be passed in the error message.
        foreach($dataSheet->getRows() as $idx => $row){
            $resRow = array();
            $resSingleRow = null;
            //evaluate all conditons regarding the current row in the datasheet
            foreach ($conditionGroup->getConditions() as $con){
                $resSingleCondition = $con->evaluate($dataSheet, $idx);
                // interpret the result of the current expression
                switch (true){
                    case $operator == EXF_LOGICAL_AND && $resSingleCondition === false:
                        $resSingleRow = false;
                        break;
                    case $operator == EXF_LOGICAL_OR && $resSingleCondition === true:
                        $resSingleRow = true;
                        $errorCondition = $con;
                        break;
                    case $operator == EXF_LOGICAL_AND && $resSingleCondition === true:
                        // If the conditions are combined by an AND-operator, and the item found is true, then
                        // store the current condition as an error-conditon in beforehand, just in case
                        // the whole expressiongroup turns out to be true.
                        // By that there will always be an expression to display if the deletion is forbidden.
                        $errorCondition = $con;
                    default:
                        // If the result of the current conditiongroup isn't already concluded, attatch the
                        // result for the current statement to an array.
                        $resRow[] = $resSingleCondition;
                }
                //break if the result of the whole expressiongroup has already been concluded
                if ($resSingleRow !== null){
                    break;
                }
            }
            
            // if the result isn't concluded yet, analyze the array with the returnvalues of every evaluation made on the current row
            if ($resSingleRow === null){
                switch ($operator){
                    case EXF_LOGICAL_AND:
                        $resSingleRow = (in_array(false, $resRow, true) === false);
                        break;
                    case EXF_LOGICAL_OR:
                        $resSingleRow = in_array(true, $resRow, true);
                        break;
                    case EXF_LOGICAL_XOR:
                        $resSingleRow = count(array_filter($resRow, function(bool $val){return $val === true;})) === 1;
                        break;
                    default:
                        throw new RuntimeException('Unsupported logical operator "' . $operator . '" in condition group "' . $conditionGroup->toString() . '"!');
                }
            }
            
            // if one single row in the datasheet is found to fulfill the given set of conditions, break.
            if ($resSingleRow === true){
                $result = true;
                break;
            }
        }

        if ($result === true){
            $errorRowDescriptor = '';
            
            // check if the regarding row has an alias for throwing in the exeption
            if ($labelAttributeAlias !== null && $row[$labelAttributeAlias] !== null){
                $errorRowDescriptor = '"' . $row[$labelAttributeAlias] . '"'; 
            }
            // if not, just use the position of the crucial datarow in the current selection
            if ($errorRowDescriptor == ''){
                $errorRowDescriptor = $idx + 1;
            }
            
            throw new DataSheetDeleteForbiddenError($dataSheet, $this->translate('BEHAVIOR.UNDELETABLEBEHAVIOR.DELETE_FORBIDDEN_ERROR', 
                                                                                    ['%row%' => $errorRowDescriptor, 
                                                                                     '%expression%' => $errorCondition->toString(), 
                                                                                     '%behavior%' => $this->getAlias(), 
                                                                                     '%object%' => $dataSheet->getMetaObject()->getAlias()]));    
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
    
    /**
     * 
     * @param string $messageId
     * @param array $placeholderValues
     * @param float $pluralNumber
     * @return string
     */
    protected function translate(string $messageId, array $placeholderValues = null, float $pluralNumber = null) : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($messageId, $placeholderValues, $pluralNumber);
    }
}