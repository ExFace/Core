<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractValidatingBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\DataSheets\DataCheckFailedErrorMultiple;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;

/**
 * Validates any proposed changes made to the monitored data and rejects invalid changes.
 * 
 * This behavior uses negative logic. If all checks fail, the overall evaluation is successful and the proposed changes
 * will be applied to the database. If at least one check succeeds, an exception will be thrown and the proposed
 * changes will be discarded. When writing data checks think of them as violations, that you are trying to catch.
 * 
 * ### Properties:
 * 
 * - `invalid_if_on_create` executes only when data is being **created**, but **before** these changes are applied to
 * the database.
 * - `invalid_if_on_update` executes only when data is being **updated**, but **before** these changes are applied to
 * the database.
 * - `invalid_if_always` executes both when data is being **created and updated**, but **before** those changes are
 * applied to the database.
 * 
 * This behavior can react both to when the data is first created and to whenever it is changed from then on.
 * You can use any of the three `ìnvalid_if` properties to control the timing of your checks.
 * 
 * ### Placeholders:
 * 
 *   - `[#~old:alias#]`: Loads the value of the specified `alias` that is currently stored in the database.
 *   - `[#~new:alias#]`: Loads the value of the specified `alias` that would be applied to the database if this
 * validation succeeds.
 * 
 * This behavior supports the use of placeholders to give you more fine-grained control over where your dynamic values
 * are being loaded from. You can apply these placeholders to any input field inside a `invalid_if` context. However,
 * since `[#~old:alias#]` loads data currently stored in the database, it does not work while data is being created
 * (because the data doesn't exist yet).
 * 
 * This means `[#~old:alias#]` only works for `invalid_if_on_update`.
 * 
 * **NOTE:** Placeholder values are NOT formatted in order to be comparable in the conditions. If you use
 * placeholders in the error messages, format them explicitly: e.g. `[#~old:=Format(MYATTR)#]`
 * 
 * ### Example: Comparing old and new values
 * 
 * This check ensures that updated values must be greater than previous values. This might for instance be useful when
 * tracking construction progress. Since we want to compare changes, we have to use `invalid_if_on_update` to enable
 * the `[#~old:alias#]` placeholder.
 * 
 * NOTE: The property `value` can usually not read data, but because we are using a placeholder, we can bypass this
 * restriction.
 * 
 * ```
 * 
 * {
 *      "invalid_if_on_update": [
 *       {
 *          "error_text": "The entered value must be greater than the previous value!",
 *          "operator": "AND",
 *          "conditions": [
 *          {
 *              "expression": "[#~new:MesswertIst#]",
 *              "comparator": "<",
 *              "value": "[#~old:MesswertIst#]"
 *          }]
 *       }]
 * }
 * 
 * ```
 * 
 * ### Example: Using multiple `invalid_if` properties
 * 
 * In this example we have extended the previous code with a new `invalid_if_on_any`, which triggers both on creating
 * and updating our data. It checks, whether the new value lies within a range of 0 to 100. When data is being created
 * in this example, only the checks in `invalid_if_on_any` will be performed. When data is being updated, however, both
 * `invalid_if_on_any` and
 * `invalid_if_on_update` will run their checks. You can use this feature to control the timing of your checks.
 * 
 *  ```
 * 
 *  {
 *      "invalid_if_always": [
 *         {
 *            "error_text": "The entered value must lie between 0 and 100!",
 *            "operator": "AND",
 *            "conditions": [
 *            {
 *                "expression": "[#~new:MesswertIst#]",
 *                "comparator": ">=",
 *                "value": 0
 *            },
 *            {
 *                 "expression": "[#~new:MesswertIst#]",
 *                 "comparator": "<=",
 *                 "value": 100
 *            }]
 *         }],
 *       "invalid_if_on_update": [
 *        {
 *           "error_text": "The entered value must be greater than the previous value!",
 *           "operator": "AND",
 *           "conditions": [
 *           {
 *               "expression": "[#~new:MesswertIst#]",
 *               "comparator": "<",
 *               "value": "[#~old:MesswertIst#]"
 *           }]
 *        }]
 *  }
 * 
 * ```
 * 
 * ### Example: Flexible syntax
 * 
 * Finally, let's touch on some fun things you can do with our flexible tools. In this example we have used
 * placeholders to dynamically assemble a more insightful error message, as well as having used a formula to do some
 * basic arithmetic. You can get fairly creative with these features, but bear in mind that things might eventually
 * break.
 * 
 *  ```
 *  {
 *       "invalid_if_on_update": [{
 *           "error_text": "[#~old:Sektion#]: The new value for MesswertIst ([#~new:MesswertIst#]) must be greater than the previous value ([#~old:MesswertIst#])!",
 *           "operator": "AND",
 *           "conditions": [{
 *               "expression": "=Calc([#~new:MesswertIst#] - [#~old:MesswertIst#])",
 *               "comparator": "<",
 *               "value": 0
 *           }]
 *        }]
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik, Georg Bieger
 * 
 */
class ValidatingBehavior extends AbstractValidatingBehavior
{
    
    /**
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), $this->getEventHandlerToPerformChecks(), $this->getPriority());
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), $this->getEventHandlerToPerformChecks(), $this->getPriority());
        
        return $this;
    }
    
    /**
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeCreateDataEvent::getEventName(), $this->getEventHandlerToPerformChecks());
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), $this->getEventHandlerToPerformChecks());

        return $this;
    }

    protected function processValidationResult(
        DataSheetEventInterface $event, 
        ?DataCheckFailedErrorMultiple $result, 
        BehaviorLogBook $logbook): void
    {
        if(!$result) {
            $logbook->addLine('The data did not match any of the data checks.');
            return;
        }
        
        $result->setUseExceptionMessageAsTitle(true);
        
        $logbook->addLine('Rendering error message...');
        $result->updateMessage();
        
        $logbook->addLine('Throwing exception...');
        throw new BehaviorRuntimeError($this, '', null, $result, $logbook);
    }

    /**
     * Triggers only when data is being CREATED. Prevent changing a data item if any of these conditions match.
     * 
     *  ### Placeholders:
     * 
     *  - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property invalid_if_on_create
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setInvalidIfOnCreate(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->validateContextUxon($uxon, self::CONTEXT_ON_CREATE);
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_CREATE);
        return $this;
    }

    /**
     * Triggers only when data is being UPDATED. Prevent changing a data item if any of these conditions match.
     * 
     * ### Placeholders:
     * 
     *  - `[#~old:alias#]`: Loads the value the specified alias held BEFORE the event was applied.
     *  - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property invalid_if_on_update
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setInvalidIfOnUpdate(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->validateContextUxon($uxon, self::CONTEXT_ON_UPDATE);
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_UPDATE);
        return $this;
    }

    /**
     * Triggers BOTH when data is being CREATED and UPDATED. Prevent changing a data item if any of these conditions match.
     * 
     * ### Placeholders:
     * 
     * - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property invalid_if_always
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setInvalidIfAlways(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->validateContextUxon($uxon, self::CONTEXT_ON_ANY);
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_ANY);
        return $this;
    }
}