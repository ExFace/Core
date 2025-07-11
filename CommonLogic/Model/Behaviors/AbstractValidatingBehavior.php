<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataCheck;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\DataSheets\DataCheckFailedErrorMultiple;
use exface\Core\Exceptions\DataSheets\DataCheckFailedError;
use exface\Core\Exceptions\DataSheets\DataCheckRuntimeError;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Exceptions\UxonParserError;
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
 * Base class for validating behaviors
 * 
 * This class includes event handlers to perform data checks of different types when
 * data is saved. It provides built-in logic to define and perform checks on create, 
 * update or both.
 * 
 * It also provides placeholders to be used in the definition of the data checks:
 * 
 * - `[#~new:xxx#]` - referencing data or caluculations based on the data of the event
 * (= the state of the data after the change)
 * - `[#~old:xxx#]` - referencing data or calculations before the change
 * 
 * App designers can use either explicit data references in their data cheks or these
 * placeholders. The "old" data will only be loaded if it is really required in the
 * checks.
 * 
 * @see \exface\Core\Behaviors\ValidatingBehavior
 * @see \exface\Core\Behaviors\ChecklistingBehavior
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
    protected array $uxonsPerEventContext = [
        self::CONTEXT_ON_UPDATE => null,
        self::CONTEXT_ON_CREATE => null,
        self::CONTEXT_ON_ANY => null
    ];

    protected bool $inProgress = false;

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
        $logbook->addDataSheet('Event data', $eventSheet);
        $logbook->addLine('Checking **' . $eventSheet->countRows() . '** row(s) of ' . $eventSheet->getMetaObject()->__toString());
        $logbook->addIndent(+1);

        // Get datasheets.
        $oldData = null;
        if ($this->isOldDataRequired()) {
            $oldData = $this->getOldData($event);
            if ($oldData !== null) {
                $logbook->addLine('Found "old" data for ' . $oldData->getMetaObject()->__toString() . ' - can use `[#~old:...#]` placeholders');
                $logbook->addDataSheet('Old data', $oldData);
                try {
                    $newData = $eventSheet->copy()->sortLike($oldData);
                    $logbook->addLine('Sorting event data in the same way as "old" data, so that row numbers match');
                    $logbook->addDataSheet('Event data resorted', $newData);
                } catch (DataSheetRuntimeError $e) {
                    throw new BehaviorRuntimeError($this, 'Failed to sort event data according to "old" data!', $e->getAlias(), $e, $logbook);
                }

            } else {
                $newData = $eventSheet;
                $logbook->addLine('No "old" data available - cannot use `[#~old:...#]` placeholders');
            }
        } else {
            $logbook->addLine('No "old" data required');
            $newData = $eventSheet;
        }

        // Check, if the event data has rows with multiple UIDs (= delimited list of UIDs). This would be the
        // case on mass updates by UID. In a validation behavior we need to check each UID separately though.
        // So we copy the data sheet and split those aggregated rows, so that we have a single UID for every
        // row.
        // TODO this is probably incompatible with old-new-data comparisons, because we change the new data here.
        // Would it be better to throw an error in this case?
        if ($newData->hasUidColumn() && $newData->getUidColumn()->hasValueLists()) {
            $logbook->addLine('Event data had at least one row with multiple UIDs - splitting rows to have single UID per row.');
            $logbook->addIndent(+1);
            $newDataPerUid = $newData->copy();
            $newDataPerUid->getUidColumn()->splitRowsWithValueLists();

            $logbook->addLine('Now **' . $newDataPerUid->countRows() . '** row(s) have a single UID each');
            $logbook->addDataSheet('Event data per UID', $newDataPerUid);

            // If we have other (non-UID) columns with value lists, we cannot be really sure if they resulted from the
            // same aggregation process, that produced our UID lists. On the other hand, our data sheet is already
            // completely detached from the original event data, so we can simply remove these columns and force
            // checks to read that data if needed.
            // TODO this will probably make comparisons between new and old data impossible
            foreach ($newDataPerUid->getColumns() as $col) {
                if ($col->hasValueLists() && ! $col->hasAggregator()) {
                    $logbook->addLine('Column `' . $col->getName() . '` has become ambiguous because it includes values lists too - **removing column** from data to be processed');
                    $newDataPerUid->getColumns()->remove($col);
                }
            }

            $logbook->addIndent(-1);
        } else {
            $newDataPerUid = $newData;
        }

        // TODO what about mass updates by filters? Shouldn't we throw an error here?
        
        $logbook->addLine('Loading data checks relevant for event `' . $event::getEventName().'`...');
        $logbook->addIndent(1);
        if(! $uxon = $this->getRelevantUxons($event, $logbook)) {
            $logbook->addLine('No relevant UXONs found for event ' . $event::getEventName().'. Nothing to do here.');
            $this->inProgress = false;
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        $logbook->addIndent(-1);
        
        // Perform data checks for each validation rule.
        $error = null;
        $logbook->addSection('Performing data checks');
        foreach ($uxon as $context => $dataCheckUxon) {
            $logbook->addLine('Checks of context `' . $context . '`');
            $logbook->addIndent(1);
            // Perform data checks.
            try {
                $this->performDataChecks(
                    $dataCheckUxon,
                    $context,
                    $oldData,
                    $newDataPerUid,
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

        $logbook->addSection('Processing validation results...');
        $logbook->addIndent(1);
        $this->processValidationResult($event, $error, $logbook);
        $logbook->addIndent(-1);

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
     * @param DataSheetEventInterface           $event
     * @param DataCheckFailedErrorMultiple|null $result
     * @param BehaviorLogBook                   $logbook
     * @return void
     */
    protected abstract function processValidationResult(DataSheetEventInterface $event, ?DataCheckFailedErrorMultiple $result, BehaviorLogBook $logbook) : void;

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
            $logbook->addLine('Found **' . $result[self::CONTEXT_ON_ANY]->countProperties() . '** checks for context `'.self::CONTEXT_ON_ANY.'`.');
        }

        if($this->uxonsPerEventContext[self::CONTEXT_ON_UPDATE] !== null && $onUpdate) {
            $result[self::CONTEXT_ON_UPDATE] = $this->uxonsPerEventContext[self::CONTEXT_ON_UPDATE];
            $logbook->addLine('Found **' . $result[self::CONTEXT_ON_UPDATE]->countProperties() . '** checks for context `'.self::CONTEXT_ON_UPDATE.'`.');
        }

        if($this->uxonsPerEventContext[self::CONTEXT_ON_CREATE] !== null && !$onUpdate) {
            $result[self::CONTEXT_ON_CREATE] = $this->uxonsPerEventContext[self::CONTEXT_ON_CREATE];
            $logbook->addLine('Found **' . $result[self::CONTEXT_ON_CREATE]->countProperties() . '** checks for context `'.self::CONTEXT_ON_CREATE.'`.');
        }

        return array_count_values($result) > 0 ? $result : false;
    }

    /**
     * Performs data validation by applying the specified checks to the provided data sheets.
     *
     * @param UxonObject              $dataCheckUxon
     * @param string                  $context
     * @param DataSheetInterface|null $oldData
     * @param DataSheetInterface      $newData
     * @param LogBookInterface        $logbook
     * @return void
     */
    protected function performDataChecks(
        UxonObject          $dataCheckUxon, 
        string              $context, 
        ?DataSheetInterface $oldData,
        DataSheetInterface  $newData,
        LogBookInterface    $logbook) : void
    {
        $error = null;
        $json = $dataCheckUxon->toJson();
        $logbook->addIndent(1);

        // Validate data row by row. This is a little inefficient, but allows us to display proper row indices for
        // any errors that might occur.
        foreach ($newData->getRows() as $iRow => $row) {
            // Render placeholders.
            $renderedUxon = $this->renderUxon($json, $context, $oldData, $newData, $iRow);
            // Reduce datasheet to the relevant row.
            $checkSheet = $newData->copy();
            $checkSheet->removeRows()->addRow($row, false, false);
            // Perform data checks.
            foreach ($this->generateDataChecks($renderedUxon) as $iCheck => $check) {
                if (! $check->isApplicable($checkSheet)) {
                    continue;
                }

                try {
                    $check->check($checkSheet, $logbook);
                } catch (DataCheckFailedError $exception) {
                    $error = $error ?? new DataCheckFailedErrorMultiple('', null, null, $this->getWorkbench()->getCoreApp()->getTranslator());
                    $error->appendError($exception, $iRow + 1, false);
                } catch (\Throwable $exception) {
                    $logbook->addSection('Data check error on row ' . $iRow);
                    $logbook->addLine('> ' . $exception->getMessage());
                    $logbook->addLine('Data check ' . $iCheck . ':');
                    $logbook->addCodeBlock($dataCheckUxon->getProperty($iCheck)->toJson(true));
                    $logbook->addLine('Rendered UXON for data row ' . $iRow . ':');
                    $logbook->addCodeBlock($renderedUxon->getProperty($iCheck)->toJson(true));
                    $logbook->addLine('Data row of ' . $checkSheet->getMetaObject()->__toString() . ':');
                    $logbook->addCodeBlock(JsonDataType::encodeJson($row, true));
                    throw new BehaviorRuntimeError(
                        $this,
                        'Cannot perform data check ' . $iCheck . ' in Behavior "' . $this->getName() . '" of object ' . $this->getObject()->__toString(),
                        null,
                        $exception,
                        $logbook
                    );
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
            $logbook->addLine('Found "old" data. Caching it for use with `[#~old:` placeholders.');
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
                if ($uxon === null) {
                    continue;
                }
                if (mb_stripos($uxon->toJson(), '[#~old:') !== false) {
                    $this->requiresOldData = true;
                    break;
                }
            }
        }
        return $this->requiresOldData;
    }

    /**
     * Validates a UXON for a given event context and throws an error if it is invalid.
     * 
     * @param UxonObject           $contextUxon
     * @param string               $context
     * @param BehaviorLogBook|null $logBook
     * @return void
     */
    protected function validateContextUxon(UxonObject $contextUxon, string $context, ?BehaviorLogBook $logBook = null) : void
    {
        if($contextUxon->isArray(true)){
            return;
        }
        
        $msg = 'Invalid UXON for context "' . $context . '". Must be of type array!';
        if($logBook) {
            throw new BehaviorRuntimeError(
                $this,
                $msg,
                null,
                null,
                $logBook);
        } else {
            throw  new BehaviorConfigurationError(
                $this, 
                $msg);
        }
    }
}