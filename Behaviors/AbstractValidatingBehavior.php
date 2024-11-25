<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataCheck;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Model\Behaviors\BehaviorDataCheckList;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\DataSheets\DataCheckFailedErrorMultiple;
use exface\Core\Exceptions\DataSheets\DataCheckFailedError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\DataSheetDeleteForbiddenError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Interfaces\DataSheets\DataCheckListInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\OptionalDataRowPlaceholder;

/**
 * Validates any proposed changes made to the monitored data and collects any rejected data 
 * for further processing.
 * 
 * Extend this class to achieve specific transformations, such as rendering meaningful errors messages.
 * 
 * @see ValidatingBehavior
 * 
 * @author Georg Bieger
 */
abstract class AbstractValidatingBehavior extends AbstractBehavior
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
    
    /**
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, self::VAR_EVENT_HANDLER], $this->getPriority());
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, self::VAR_EVENT_HANDLER], $this->getPriority());
        
        return $this;
    }
    
    /**
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeCreateDataEvent::getEventName(), [$this, self::VAR_EVENT_HANDLER]);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, self::VAR_EVENT_HANDLER]);

        return $this;
    }

    /**
     * Triggers only when data is being CREATED.
     *
     *  ### Placeholders:
     *
     *  - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     *
     * @uxon-property invalid_if_on_create
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "",
     *     "value": ""}]}]
     *
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setInvalidIfOnCreate(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->uxonsPerEventContext[self::VAR_ON_CREATE] = $uxon;
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
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "",
     *     "value": ""}]}]
     *
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setInvalidIfOnUpdate(UxonObject $uxon) : AbstractValidatingBehavior
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
     * - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     *
     * @uxon-property invalid_if_always
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "",
     *     "value": ""}]}]
     *
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setInvalidIfAlways(UxonObject $uxon) : AbstractValidatingBehavior
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
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        // Perform data checks for each validation rule.
        $error = null;
        $context = $event::class;
        
        foreach ($uxon as $dataCheckUxon) {
            // Perform data checks.
            try {
                $this->performDataChecks($dataCheckUxon, $context, $previousDataSheet, $changedDataSheet);
            } catch (DataCheckFailedErrorMultiple $exception) {
                if(!$error) {
                    $error = $exception;
                } else {
                    $error->merge($exception, false);
                }
            }
        }

        if($error) {
            $this->handleError($error);
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
    }

    protected abstract function handleError(DataCheckFailedErrorMultiple $error) : void;
    
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
     * @param UxonObject              $dataCheckUxon
     * @param string                  $context
     * @param DataSheetInterface|null $previousDataSheet
     * @param DataSheetInterface      $changedDataSheet
     * @return void
     */
    protected function performDataChecks(
        UxonObject          $dataCheckUxon, 
        string              $context, 
        ?DataSheetInterface $previousDataSheet, 
        DataSheetInterface  $changedDataSheet) : void
    {
        $error = null;
        $json = $dataCheckUxon->toJson();
        
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
     * @param DataSheetInterface|null $oldData
     * @param DataSheetInterface      $newData
     * @param int                     $rowIndex
     * @return UxonObject
     */
    protected function renderUxon(
        string              $json, 
        string               $context,
        ?DataSheetInterface $oldData, 
        DataSheetInterface  $newData, 
        int                 $rowIndex) : UxonObject
    {
        $renderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        $renderer->addPlaceholder(new OptionalDataRowPlaceholder($oldData, $rowIndex, '~old:', $context, true));
        $renderer->addPlaceholder(new OptionalDataRowPlaceholder($newData, $rowIndex, '~new:', $context, true));
        
        try {
            $renderedJson = $renderer->render($json);
        } catch (\Throwable $e) {
            $message = PHP_EOL.$this->getAlias().' - '.$e->getMessage();
            throw new BehaviorRuntimeError($this, $message, null, $e);
        }
        
        // TODO 2024-09-05 geb: What happens, when the requested data cannot be found? (Error, Ignore, other?)
        return UxonObject::fromJson($renderedJson, CASE_LOWER);
    }

    /**
     * @param UxonObject $uxonObject
     * @return DataCheckListInterface
     */
    protected function generateDataChecks(UxonObject $uxonObject): DataCheckListInterface
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