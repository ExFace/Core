<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\DataSheetDeleteForbiddenError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Interfaces\DataSheets\DataCheckListInterface;
use exface\Core\CommonLogic\DataSheets\DataCheck;
use exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface;
use exface\Core\CommonLogic\Model\Behaviors\BehaviorDataCheckList;

/**
 * Prevents the deletion of data if it matches the provided conditions.
 * 
 * ## Examples
 * 
 * ### Prevent deletion of records in certain status
 * 
 * Lets asume, we have some docuements with a working state of `100` (Done),
 * `101` (Cancelled) and `102` expired. States before `100` are work-in-progress
 * while those after `100` are final. Now we need to prevent deletion of documents
 * in final states except for those cancelled.
 * 
 * ```
 * {
 *  "prevent_delete_if": [{
 *      "error_text": "Cannot delete finished documents",
 *      "operator": "AND",
 *      "conditions": [
 *          {
 *              "expression": "STATUS",
 *              "comparator": ">=",
 *              "value": 100
 *          },{
 *              "expression": "STATUS",
 *              "comparator": "!==",
 *              "value": 101
 *          }
 *      ]
 *  }]
 * }
 * 
 * ```
 * 
 * ### Prevent deletion of customers with at least one order or inquiry
 * 
 * In this case, the behavior should be attached to the `CUSTOMER` object. It will 
 * automatically read the number of the customer's orders and inquiries every time 
 * a customer is about to be deleted an compare it to `0`.
 * 
 * In this example, we use two separate checks with different error messages, but
 * we could also use a single one with an `OR` operator, of course.
 * 
 * ```
 * {
 *  "prevent_delete_if": [
 *      {
 *          "error_text": "Cannot delete customers, that have orders!",
 *          "operator": "AND",
 *          "conditions": [
 *              {
 *                  "expression": "ORDER__ID:COUNT",
 *                  "comparator": "!==",
 *                  "value": 0
 *              }
 *          ]
 *      },{
 *          "error_text": "Cannot delete customers, that have inquiries!",
 *          "operator": "AND",
 *          "conditions": [
 *              {
 *                  "expression": "INQUIRY__ID:COUNT",
 *                  "comparator": "!==",
 *                  "value": 0
 *              }
 *          ]
 *      }
 *  ]
 * }
 * 
 * ```
 * 
 * ### Only allow deletion of entries added today
 * 
 * ```
 * {
 *  "prevent_delete_if": [{
 *      "operator": "AND",
 *      "conditions": [
 *          {
 *              "expression": "CREATED_ON",
 *              "comparator": "!==",
 *              "value": 0
 *          }
 *      ]
 *  }]
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
    
    private $preventDeleteIfUxon = null;
    
    private $dataCheckList = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'handleOnBeforeDelete'], $this->getPriority());
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'handleOnBeforeDelete']);
        
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
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        $dataSheet = $eventDataSheet->copy();
        
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
        
        foreach ($this->getDataChecks() as $check) {
            if ($check->isApplicable($dataSheet)) {
                try {
                    $check->check($dataSheet);
                } catch (DataCheckExceptionInterface $e) {
                    if (null !== ($badData = $e->getBadData()) && $badData->countRows() === 1) {
                        $rows = $badData->getRows();
                        $idx = array_key_first($rows);
                        $row = $rows[$idx];
                        // check if the regarding row has an alias for throwing in the exeption
                        if ($labelAttributeAlias !== null && $row[$labelAttributeAlias] !== null){
                            $message = $this->translate('BEHAVIOR.UNDELETABLEBEHAVIOR.DELETE_FORBIDDEN_ERROR',[
                                '%row%' => '"' . $row[$labelAttributeAlias] . '"',
                                '%object%' => $dataSheet->getMetaObject()->getName()
                            ]);
                        } else {
                            $message = $this->translate('BEHAVIOR.UNDELETABLEBEHAVIOR.DELETE_FORBIDDEN_ROWS_ERROR',[
                                '%row%' => $idx + 1,
                                '%object%' => $dataSheet->getMetaObject()->getName()
                            ]);
                        }
                    }
                    
                    $message .= ($message !== null ? ' ' : '') . $e->getMessage();
                    
                    throw (new DataSheetDeleteForbiddenError($dataSheet, $message))->setUseExceptionMessageAsTitle(true); 
                }
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
        return;
    }
    
    /**
     * @deprecated use setPreventDeleteIf() instead
     * 
     * @param UxonObject $uxon
     * @return UndeletableBehavior
     */
    public function setConditionGroup(UxonObject $uxon) : UndeletableBehavior
    {
        $this->setPreventDeleteIf(new UxonObject([
            [
                $uxon->toArray()
            ]
        ]));
        return $this;
    }
    
    /**
     * Prevent deleting a data item if any of these conditions match
     * 
     * @uxon-property prevent_delete_if
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $arrayOfDataChecks
     * @return UndeletableBehavior
     */
    protected function setPreventDeleteIf(UxonObject $arrayOfDataChecksUxon) : UndeletableBehavior
    {
        $this->preventDeleteIfUxon = $arrayOfDataChecksUxon;
        $this->dataCheckList = null;
        return $this;
    }
    
    /**
     * 
     * @return DataCheckListInterface
     */
    protected function getDataChecks() : DataCheckListInterface
    {
        if ($this->dataCheckList === null) {
            $this->dataCheckList = new BehaviorDataCheckList($this->getWorkbench(), $this);
            foreach ($this->preventDeleteIfUxon as $uxon) {
                $this->dataCheckList->add(new DataCheck($this->getWorkbench(), $uxon));
            }
        }
        return $this->dataCheckList;
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