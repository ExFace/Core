<?php
namespace exface\Core\Behaviors;

use exface\Core\Behaviors\PlaceholderConfigs\TplConfigExtensionOldData;
use exface\Core\Behaviors\PlaceholderConfigs\TemplateRendererConfig;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\DataSheets\DataCheckFailedErrorMultiple;
use exface\Core\Exceptions\DataSheets\DataCheckFailedError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\DataSheetDeleteForbiddenError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Interfaces\DataSheets\DataCheckListInterface;
use exface\Core\CommonLogic\DataSheets\DataCheck;
use exface\Core\CommonLogic\Model\Behaviors\BehaviorDataCheckList;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;

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
 * {
 *       "invalid_if_on_update": [
 *        {
 *           "error_text": "[#~old:Sektion#]: The new value for MesswertIst ([#~new:MesswertIst#]) must be greater than
 * the previous value ([#~old:MesswertIst#])!",
 *           "operator": "AND",
 *           "conditions": [
 *           {
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
class ValidatingBehavior extends AbstractBehavior
{
    const VAR_EVENT_HANDLER = "handleOnChange";

    const VAR_ON_CREATE = "on_create";

    const VAR_ON_UPDATE = "on_update";

    const VAR_ON_ANY = "always";
    
    // TODO 2024-08-29 geb: Config could support additional behaviors: throw, default
    // TODO 2024-09-05 geb: Might need more fine grained control, since the behaviour may be triggered in unexpected contexts (e.g. created for one dialogue, triggered by another)
    private array $uxonsPerEventContext = array(
        self::VAR_ON_UPDATE => null,
        self::VAR_ON_CREATE => null,
        self::VAR_ON_ANY => null
    );
    
    private TemplateRendererConfig $config;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, self::VAR_EVENT_HANDLER], $this->getPriority());
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, self::VAR_EVENT_HANDLER], $this->getPriority());
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeCreateDataEvent::getEventName(), [$this, self::VAR_EVENT_HANDLER]);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, self::VAR_EVENT_HANDLER]);

        return $this;
    }

    /**
     * Triggers only when data is being CREATED. Prevent changing a data item if any of these conditions match.
     *
     *  ### Placeholders:
     * 
     *  - `[#~new:alias#]`: Loads the value of the specified `alias` that would be applied to the database if this
     * validation succeeds.
     *
     * @uxon-property invalid_if_on_create
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "",
     *     "value": ""}]}]
     *
     * @param UxonObject $uxon
     * @return ValidatingBehavior
     */
    public function setInvalidIfOnCreate(UxonObject $uxon) : ValidatingBehavior
    {
        $this->uxonsPerEventContext[self::VAR_ON_CREATE] = $uxon;
        return $this;
    }

    /**
     * Triggers only when data is being UPDATED. Prevent changing a data item if any of these conditions match.
     *
     * ### Placeholders:
     * 
     *  - `[#~old:alias#]`: Loads the value for the specified alias that is currently stored in the database.
     *  - `[#~new:alias#]`: Loads the value of the specified `alias` that would be applied to the database if this
     * validation succeeds.
     *
     * @uxon-property invalid_if_on_update
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "",
     *     "value": ""}]}]
     *
     * @param UxonObject $uxon
     * @return ValidatingBehavior
     */
    public function setInvalidIfOnUpdate(UxonObject $uxon) : ValidatingBehavior
    {
        $this->uxonsPerEventContext[self::VAR_ON_UPDATE] = $uxon;
        return $this;
    }

    /**
     * Triggers BOTH when data is being CREATED and UPDATED. Prevent changing a data item if any of these conditions
     * match.
     *
     * ### Placeholders:
     * 
     * - `[#~new:alias#]`: Loads the value of the specified `alias` that would be applied to the database if this
     * validation succeeds.
     *
     * @uxon-property invalid_if_always
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "",
     *     "value": ""}]}]
     *
     * @param UxonObject $uxon
     * @return ValidatingBehavior
     */
    public function setInvalidIfAlways(UxonObject $uxon) : ValidatingBehavior
    {
        $this->uxonsPerEventContext[self::VAR_ON_ANY] = $uxon;
        return $this;
    }

    /**
     * Handles any change requests for the associated data and decides whether the proposed are valid or
     * need to be rejected.
     *
     * @param OnBeforeDeleteDataEvent $event
     * @throws RuntimeException
     * @throws DataSheetDeleteForbiddenError
     * @throws \Exception
     */
    public function handleOnChange(DataSheetEventInterface $event) : void
    {
        if ($this->isDisabled()) {
            return;
        }

        // Get datasheets.
        if ($event instanceof OnBeforeUpdateDataEvent) {
            $onUpdate = true;
            $previousDataSheet = $event->getDataSheetWithOldData();
            $changedDataSheet = $event->getDataSheet()->copy()->sortLike($previousDataSheet);
        } else {
            $onUpdate = false;
            $previousDataSheet = null;
            $changedDataSheet = $event->getDataSheet();
        }

        if(!$uxon = $this->getRelevantUxons($onUpdate)) {
            return;
        }

        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $changedDataSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }

        if(empty($this->config)) {
            $this->config = new TemplateRendererConfig();
            $this->config->addExtension(new TplConfigExtensionOldData());
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        // Perform data checks for each validation rule.
        $error = null;
        $context = get_class($event);
        foreach ($uxon as $dataCheckUxon) {
            // Check UXON for invalid placeholders.
            try {
                $json = $dataCheckUxon->toJson();
                $this->config->checkStringForInvalidPlaceholders($context, $json);
            } catch (InvalidArgumentException $e) {
                throw new BehaviorRuntimeError($this, $e->getMessage(), '7X9TCJ3');
            }
            
            // Perform data checks.
            try {
                $this->performDataChecks($json, $context, $previousDataSheet, $changedDataSheet);
            } catch (DataCheckFailedErrorMultiple $exception) {
                if(!$error) {
                    $error = $exception;
                } else {
                    $error->merge($exception, false);
                }
            }
        }

        if($error) {
            $error->setUseExceptionMessageAsTitle(true);
            $error->regenerateMessage();
            throw $error;
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }

    /**
     * @param bool $onUpdate
     * @return array|bool
     */
    protected function getRelevantUxons(bool $onUpdate) : array | bool
    {
        $result = array();

        if($this->uxonsPerEventContext[self::VAR_ON_ANY] !== null) {
            $result['invalid_if_'.self::VAR_ON_ANY] = $this->uxonsPerEventContext[self::VAR_ON_ANY];
        }

        if($this->uxonsPerEventContext[self::VAR_ON_UPDATE] !== null && $onUpdate) {
            $result['invalid_if_'.self::VAR_ON_UPDATE] = $this->uxonsPerEventContext[self::VAR_ON_UPDATE];
        }

        if($this->uxonsPerEventContext[self::VAR_ON_CREATE] !== null && !$onUpdate) {
            $result['invalid_if_'.self::VAR_ON_CREATE] = $this->uxonsPerEventContext[self::VAR_ON_CREATE];
        }

        return array_count_values($result) > 0 ? $result : false;
    }

    /**
     * Performs data validation by applying the specified checks to the provided data sheets.
     *
     * @param string                  $json
     * @param string                  $context
     * @param DataSheetInterface|null $previousDataSheet
     * @param DataSheetInterface      $changedDataSheet
     * @return void
     */
    protected function performDataChecks(
        string                 $json, 
        string                 $context, 
        ?DataSheetInterface    $previousDataSheet, 
        DataSheetInterface     $changedDataSheet) : void
    {
        $error = null;

        // Validate data row by row. This is a little inefficient, but allows us to display proper row indices for any errors that might occur.
        foreach ($changedDataSheet->getRows() as $index => $row) {
            // Render placeholders.
            $renderedUxon = $this->renderUxon($json, $context, $previousDataSheet, $changedDataSheet, $index);
            // Reduce datasheet to the relevant row.
            $checkSheet = $changedDataSheet->copy();
            $checkSheet->removeRows()->addRow($row);
            // Perform data checks.
            foreach ($this->generateDataChecks($renderedUxon) as $check) {
                if (!$check->isApplicable($changedDataSheet)) {
                    continue;
                }

                try {
                    $check->check($checkSheet);
                } catch (DataCheckFailedError $exception) {
                    $error = $error ?? new DataCheckFailedErrorMultiple('', null, null, $this->getWorkbench()->getCoreApp()->getTranslator());
                    $error->appendError($exception, $index + 1, false);
                }
            }
        }

        if($error) {
            throw $error;
        }
    }

    /**
     * Renders all placeholders present in the provided UXON.
     *
     * @param string                  $json
     * @param string                  $context
     * @param DataSheetInterface|null $previousDataSheet
     * @param DataSheetInterface      $changedDataSheet
     * @param int                     $rowIndex
     * @return UxonObject
     */
    private function renderUxon(
        string              $json, 
        string              $context,
        ?DataSheetInterface $previousDataSheet, 
        DataSheetInterface  $changedDataSheet, 
        int                 $rowIndex) : UxonObject
    {
        $placeHolderRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        
        $this->config->applyResolversForContext($placeHolderRenderer, $context, [
            new DataRowPlaceholders($previousDataSheet ?? $changedDataSheet, $rowIndex, TplConfigExtensionOldData::PREFIX_OLD),
            new DataRowPlaceholders($changedDataSheet, $rowIndex, TplConfigExtensionOldData::PREFIX_NEW)
        ]);
        
        // TODO 2024-09-05 geb: What happens, when the requested data cannot be found? (Error, Ignore, other?)
        return UxonObject::fromJson($placeHolderRenderer->render($json), CASE_LOWER);
    }

    /**
     * @param UxonObject $uxonObject
     * @return DataCheckListInterface
     */
    protected function generateDataChecks(UxonObject $uxonObject) : DataCheckListInterface
    {
        $dataCheckList = new BehaviorDataCheckList($this->getWorkbench(), $this);
        foreach ($uxonObject as $uxon) {
            $dataCheckList->add(new DataCheck($this->getWorkbench(), $uxon));
        }

        return $dataCheckList;
    }

    /**
     *
     * @param string $messageId
     * @param array|null $placeholderValues
     * @param float|null $pluralNumber
     * @return string
     */
    protected function translate(string $messageId, array $placeholderValues = null, float $pluralNumber = null) : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($messageId, $placeholderValues, $pluralNumber);
    }
}