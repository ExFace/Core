<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataCheck;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\DataSheets\DataCheckFailedErrorMultiple;
use exface\Core\Exceptions\DataSheets\DataCheckFailedError;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Events\DataChangeEventInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\DataSheetDeleteForbiddenError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Interfaces\DataSheets\DataCheckListInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\OptionalDataRowPlaceholder;

/**
 * Validates any proposed changes made to the monitored data and collects any conflicting data 
 * for further processing.
 * 
 * Extend this class to achieve specific transformations, such as rejecting invalid changes, by throwing
 * a meaningful error message.
 * 
 * @see ValidatingBehavior
 * 
 * @author Georg Bieger
 */
abstract class AbstractValidatingBehavior extends AbstractBehavior
{
    const CONTEXT_ON_CREATE = "on_create";

    const CONTEXT_ON_UPDATE = "on_update";

    const CONTEXT_ON_ANY = "always";
    
    // TODO 2024-08-29 geb: Config could support additional behaviors: throw, default
    // TODO 2024-09-05 geb: Might need more fine grained control, since the behaviour may be triggered in unexpected contexts (e.g. created for one dialogue, triggered by another)
    private array $uxonsPerEventContext = [
        self::CONTEXT_ON_UPDATE => null,
        self::CONTEXT_ON_CREATE => null,
        self::CONTEXT_ON_ANY => null
    ];

    private bool $inProgress = false;

    private $requiresOldData = null;

    private $oldData = [];

    protected function getEventHandlerToPerformChecks() : callable
    {
        return [$this, 'onChangePerformChecks'];
    }
    
    /**
     * Assign a UXON definition to a specific event context. 
     * 
     * NOTE: It is recommended to use the `CONTEXT` constants as identifiers.
     * 
     * @param UxonObject $uxon
     * @param string     $eventContext
     * @return $this
     */
    protected function setUxonForEventContext(UxonObject $uxon, string $eventContext) : static
    {
        $this->uxonsPerEventContext[$eventContext] = $uxon;
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
    public function onChangePerformChecks(DataSheetEventInterface $event) : void
    {
        $eventSheet = $event->getDataSheet();
        if (! $this->isRelevantData($eventSheet)) {
            return;
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));

        $this->inProgress = true;
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logbook->addDataSheet('New data', $eventSheet);
        $logbook->addLine('Checking ' . $eventSheet->countRows() . ' rows of ' . $eventSheet->getMetaObject()->__toString());
        
        // Get datasheets.
        if ($this->isOldDataRequired()) {
            $previousDataSheet = $this->getOldData($event);
            if ($previousDataSheet !== null) {
                $logbook->addLine('Found "old" data for ' . $previousDataSheet->getMetaObject()->__toString() . ' - can use `[#old:...#]` placeholders');
                $logbook->addDataSheet('Old data', $previousDataSheet);
                try {
                    $changedDataSheet = $eventSheet->copy()->sortLike($previousDataSheet);
                } catch (DataSheetRuntimeError $e) {
                    $logbook->addDataSheet('New data (unsorted)', $eventSheet);
                    throw new BehaviorRuntimeError($this, "Failed to align post-transaction data!", $e->getAlias(), $e, $logbook);
                }

            } else {
                $changedDataSheet = $eventSheet;
                $logbook->addLine('No "old" data available - cannot use `[#old:...#]` placeholders');
            }
        }
        
        $logbook->addDataSheet('New data', $changedDataSheet);
        $logbook->addLine('Found new data for ' . $changedDataSheet->getMetaObject()->__toString());
        
        $logbook->addLine('Loading relevant UXON definitions for context '.$event::getEventName().'...');
        $logbook->addIndent(1);
        if(!$uxon = $this->getRelevantUxons($event, $logbook)) {
            $logbook->addLine('No relevant UXONs found for event '.$event::getEventName().'. Nothing to do here.');
            $this->inProgress = false;
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        $logbook->addIndent(-1);
        
        // Perform data checks for each validation rule.
        $error = null;
        $logbook->addLine('Processing UXON definitions per context...');
        $logbook->addIndent(1);
        foreach ($uxon as $context => $dataCheckUxon) {
            $logbook->addLine('Context '.$context);
            $logbook->addIndent(1);
            // Perform data checks.
            try {
                $this->performDataChecks(
                    $dataCheckUxon,
                    $context,
                    $previousDataSheet,
                    $changedDataSheet,
                    $logbook);
            } catch (DataCheckFailedErrorMultiple $exception) {
                $logbook->addLine('At least one data check applied to the input data:');
                $logbook->addException($exception);
                if(!$error) {
                    $error = $exception;
                } else {
                    $error->merge($exception, false);
                }
            }
            $logbook->addIndent(-1);
        }
        $logbook->addIndent(-1);
        
        if($error) {
            $logbook->addLine('Processing validation results...');
            $logbook->addIndent(1);
            $this->processValidationResult($error, $logbook);
            $logbook->addIndent(-1);
        }

        $this->inProgress = false;
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    /**
     * Process the results of the validation.
     *
     * The validation results are represented as a collection of multiple instances of `DataCheckFailedError`.
     * While the class name suggests error handling, you should view these objects as neutral data containers
     * that you can process any way you like.
     *
     * @param DataCheckFailedErrorMultiple $result
     * @param BehaviorLogBook              $logbook
     * @return void
     */
    protected abstract function processValidationResult(DataCheckFailedErrorMultiple $result, BehaviorLogBook $logbook) : void;

    /**
     * @param EventInterface  $event
     * @param BehaviorLogBook $logbook
     * @return array|bool
     */
    protected function getRelevantUxons(EventInterface $event, BehaviorLogBook $logbook) : array | bool
    {
        $result = array();
        $onUpdate = $event instanceof OnBeforeUpdateDataEvent || $event instanceof OnUpdateDataEvent;

        if($this->uxonsPerEventContext[self::CONTEXT_ON_ANY] !== null) {
            $result[self::CONTEXT_ON_ANY] = $this->uxonsPerEventContext[self::CONTEXT_ON_ANY];
            $logbook->addLine('Found UXON definition for "'.self::CONTEXT_ON_ANY.'".');
        }

        if($this->uxonsPerEventContext[self::CONTEXT_ON_UPDATE] !== null && $onUpdate) {
            $result[self::CONTEXT_ON_UPDATE] = $this->uxonsPerEventContext[self::CONTEXT_ON_UPDATE];
            $logbook->addLine('Found UXON definition for "'.self::CONTEXT_ON_UPDATE.'".');
        }

        if($this->uxonsPerEventContext[self::CONTEXT_ON_CREATE] !== null && !$onUpdate) {
            $result[self::CONTEXT_ON_CREATE] = $this->uxonsPerEventContext[self::CONTEXT_ON_CREATE];
            $logbook->addLine('Found UXON definition "'.self::CONTEXT_ON_CREATE.'".');
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
     * @param LogBookInterface        $logbook
     * @return void
     */
    protected function performDataChecks(
        UxonObject          $dataCheckUxon, 
        string              $context, 
        ?DataSheetInterface $previousDataSheet, 
        DataSheetInterface  $changedDataSheet,
        LogBookInterface    $logbook) : void
    {
        $error = null;
        $json = $dataCheckUxon->toJson();
        $logbook->addIndent(1);

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
                    $check->check($checkSheet, $logbook);
                } catch (DataCheckFailedError $exception) {
                    $error = $error ?? new DataCheckFailedErrorMultiple('', null, null, $this->getWorkbench()->getCoreApp()->getTranslator());
                    $error->appendError($exception, $index + 1, false);
                }
            }
        }
        $logbook->addIndent(-1);

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
        $renderer->addPlaceholder(new OptionalDataRowPlaceholder($newData, $rowIndex, '~new:', $context, true));
        if ($this->isOldDataRequired()) {
            $renderer->addPlaceholder(new OptionalDataRowPlaceholder($oldData, $rowIndex, '~old:', $context, true));
        }
        
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

    protected function getEventHandlerToCacheOldData() : callable
    {
        return [$this, "onBeforeUpdateCacheOldData"];
    }

    /**
     * Caches pre-transaction data, to be retrieved later.
     * 
     * Remember to clear the cache, once you've loaded data from it!
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function onBeforeUpdateCacheOldData(OnBeforeUpdateDataEvent $event) : void
    {
        $eventSheet = $event->getDataSheet();
        if (! $this->isRelevantData($eventSheet)) {
            return;
        }

        if (! $this->isOldDataRequired()) {
            return;
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logbook->addLine('Caching "old" data...');
        
        $oldData = $event->getDataSheetWithOldData();
        $this->cacheOldData($eventSheet, $oldData);
        
        if($oldData !== null) {
            $logbook->addLine('Found "old" data. Caching it for use with `[#old:` placeholders.');
            $logbook->addDataSheet("Old data", $oldData);
        } else {
            $logbook->addLine('No "old" data found!');
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
    }

    protected function isRelevantData(DataSheetInterface $dataSheet) : bool
    {
        if ($this->isDisabled() || $this->inProgress) {
            return false;
        }

        if(! $dataSheet->getMetaObject()->isExactly($this->getObject())) {
            return false;
        }

        return true;
    }

    /**
     * Try to load a datasheet with OnBefore-event data. Returns NULL if no such datasheet was found. 
     * 
     * @param EventInterface $event
     * @return DataSheetInterface|null
     */
    protected function getOldData(DataSheetEventInterface $event): ?DataSheetInterface
    {
        if ($event instanceof DataChangeEventInterface) {
            return $event->getDataSheetWithOldData();
        }

        $newData = $event->getDataSheet();
        foreach($this->oldData as $item) {
            if ($item['newData'] === $newData) {
                return $item['oldData'];
            }
        }
        return null;
    }

    protected function cacheOldData(DataSheetInterface $newData, DataSheetInterface $oldData) : AbstractValidatingBehavior
    {
        $this->oldData[] = [
            'oldData' => $oldData,
            'newData' => $newData
        ];
        return $this;
    }

    /**
     * 
     * @return bool
     */
    protected function isOldDataRequired() : bool
    {
        if ($this->requiresOldData === null) {
            $this->requiresOldData = false;
            foreach ($this->uxonsPerEventContext as $uxon) {
                if (mb_stripos($uxon->toJson(), '[#~old:') !== false) {
                    $this->requiresOldData = true;
                    break;
                }
            }
        }
        return $this->requiresOldData;
    }
}