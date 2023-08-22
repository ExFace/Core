<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\Actions\Traits\iPrefillWidgetTrait;
use exface\Core\Events\Widget\OnPrefillDataLoadedEvent;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Events\Widget\OnBeforePrefillEvent;
use exface\Core\Interfaces\Widgets\iHaveDefaultValue;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Exceptions\Actions\ActionRuntimeError;

/**
 * Exports the prefill data sheet for the target widget.
 * 
 * This action allows to fetch the data, that would be used to prefill a widget. This
 * can be used to fill forms asynchronously - for example:
 * 
 * - Reload the data for a form without rerendering it
 * - Render an editor dialog once and merely switch data sets when opening it for
 * different objects
 * - Allow the user to navigate to the next/previos object right in an detail
 * widget via buttons
 * 
 * The prefill data is fetched in the same way, as a `ShowWidget` action would do.
 * This action also has the the same `prefill_xxx` properties. Refer to the documentation
 * of `ShowWidget` for more details.
 * 
 * @see ShowWidget
 * 
 * @author Andrej Kabachnik
 *
 */
class ReadPrefill extends ReadData implements iPrefillWidget
{
    use iPrefillWidgetTrait {
        getPrefillWithFilterContext as getPrefillWithFilterContextViaTrait;
        getPrefillWithInputData as getPrefillWithInputDataViaTrait;
        getPrefillWithPrefillData as getPrefillWithPrefillDataViaTrait;
        getPrefillWithDefaults as getPrefillWithDefaultsViaTrait;
        getPrefillDataPreset as getPrefillDataPresetViaTrait;
        hasPrefillDataPreset as hasPrefillDataPresetViaTrait;
        getPrefillDataSheet as getPrefillDataSheetViaTrait;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ReadData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $mainSheet = null;
        $log = '';
        $logSheets = [];
        $targetWidget = $this->getWidgetToReadFor($task);
        
        // If the prefill is read for a widget opened by a trigger (e.g. a button),
        // any mappers or checks used on the original action of the button must be
        // applied to the prefill too! 
        // Note, however, that checks/mappers defined inside the button will be applied 
        // automatically as the UXON from the button is imported into the prefill action too.
        // But if the button calls an object action from the meta model, that has checks/mappers
        // defined, they will not be part of the imported UXON and need to be added manually here.
        if (null !== $showWidgetAction = $this->getPrefillTriggerAction($task)) {
            // Inherit all checks
            // IDEA should we only get checks, that are different from those alread in the prefill action?
            foreach ($showWidgetAction->getInputChecks() as $check) {
                $this->getInputChecks()->add($check);
            }
            
            // Inherit mappers for objects, that are not handled by already existing mappers
            foreach ($showWidgetAction->getInputMappers() as $mapper) {
                if (null === $this->getInputMapper($mapper->getFromMetaObject())) {
                    $this->addInputMapper($mapper);
                }
            }
            foreach ($showWidgetAction->getOutputMappers() as $mapper) {
                if (null === $this->getOutputMapper($mapper->getFromMetaObject())) {
                    $this->addOutputMapper($mapper);
                }
            }
        }
        
        // Normally, if we know, which widget to prefill, use the normal prefill logic from the iPrefillWidgetTrait
        // Otherwise get the input/prefill data and refresh it if neccessary
        if ($targetWidget !== null) {
            $prefillSheets = $this->getPrefillDataFromTask($targetWidget, $task, $log, $logSheets);
            $mainSheet = $prefillSheets[0];
            $mainSheet = $this->getPrefillDataFromFilterContext($targetWidget, $mainSheet, $task, $log);
        } else {
            $log .= '- Cannot determine widget to prefill - falling back to use of input/prefill data only!' . PHP_EOL;
            try {
                $mainSheet = $this->getInputDataSheet($task);
                $logSheets['Input data'] = $mainSheet;
                $log .= '- Input data found:' . PHP_EOL;
                $log .= '   - Object: "' . $mainSheet->getMetaObject()->getAliasWithNamespace() . '"' . PHP_EOL;
                $log .= '   - Rows: ' . $mainSheet->countRows() . PHP_EOL;
                $log .= '   - Filters: ' . ($mainSheet->getFilters()->countConditions() + $mainSheet->getFilters()->countNestedGroups()) . PHP_EOL;
            } catch (ActionInputMissingError $e) {
                $log .= '- No input data to use' . PHP_EOL;
                // ignore missing data - continue with the next if
            }
            if ($mainSheet === null || $mainSheet->isBlank() && $task->hasPrefillData()) {
                $mainSheet = $task->getPrefillData();
                $logSheets['Input data'] = $mainSheet;
                $log .= '- Prefill data found:' . PHP_EOL;
                $log .= '   - Object: "' . $mainSheet->getMetaObject()->getAliasWithNamespace() . '"' . PHP_EOL;
                $log .= '   - Rows: ' . $mainSheet->countRows() . PHP_EOL;
                $log .= '   - Filters: ' . ($mainSheet->getFilters()->countConditions() + $mainSheet->getFilters()->countNestedGroups()) . PHP_EOL;
            } else {
                $log .= '- No input data to use' . PHP_EOL;
            }
            
            // We don't need the total row count for prefills.
            $mainSheet->setAutoCount(false);
            
            // IDEA are there other ways to load more data, than use UID-filters?
            $canLoadMoreData = $mainSheet->hasUidColumn(true);
            
            if ($mainSheet->isEmpty()) {
                $log .= '- Did not find any prefill data till now - use filter context only.' . PHP_EOL;
                $mainSheet = $this->getPrefillDataFromFilterContext($targetWidget, $mainSheet, $task, $log);
                $canLoadMoreData = false;
            } else {
                if ($mainSheet->hasUidColumn(true)) {
                    $mainSheet->getFilters()->addConditionFromColumnValues($mainSheet->getUidColumn());
                } /*elseif ($data_sheet->getFilters()->isEmpty() === false) {
                return ResultFactory::createDataResult($task, $data_sheet->removeRows());
                }*/
            }
            
            // Reed data if it is not fresh
            if ($canLoadMoreData === true && $mainSheet->isFresh() === false) {
                $log .= '- Refreshing data' . PHP_EOL;
                $mainSheet->dataRead();
                
                // Check fresh prefill data again if it is still valid because actual input data
                // for the action could have been old and user might actually not be allowed
                // to trigger showing a dialog anymore and therefor trigger the prefill
                // This can occur if user did load data in a table and the another user changed a data entry
                // after the first user did load the table. The first user can then select an entry and press a button
                // to show a dialog, for example to change a status with ading a commentary.
                // With up to date data that action wouldn't be allowed, but as the user still has old data shown they can
                // trigger the action initially.
                // Therefor we should check again after we load the actual data in the prefill if the prefill ist actually allowed
                // by checking again against the checks of the trigger action.
                $this->validateInputData($mainSheet);
            } else {
                $log .= '- Refresh is not required or not possible' . PHP_EOL;
            }
        }
        
        if ($mainSheet === null) {
            $log .= '- No prefill data found so far: creating an empty data sheet.' . PHP_EOL;
            $mainSheet = DataSheetFactory::createFromObject($this->getMetaObject());
            $mainSheet->setAutoCount(false);
        }
        
        $prefillWithDefaults = $this->getPrefillWithDefaults($task);
        $log .= '- Property `prefill_with_input_data` is `' . ($prefillWithDefaults === null ? 'null' : ($prefillWithDefaults ? 'true' : 'false')) . '`.' . PHP_EOL;
        if ($prefillWithDefaults !== false) {
            $defaults = [];
            // Add event listeners to see, what the prefill would do
            // 1) Before a widget is prefilled, remember its default value
            $this->getWorkbench()->eventManager()->addListener(OnBeforePrefillEvent::getEventName(), function(OnBeforePrefillEvent $event) use (&$defaults) {
                $widget = $event->getWidget();
                if (($widget instanceof iHaveDefaultValue) && ($widget instanceof iShowDataColumn) && $widget->hasDefaultValue() && $widget->getMetaObject()->is($event->getDataSheet()->getMetaObject())) {
                    $value = $widget->getDefaultValue();
                    if ($widget instanceof iHaveValue) {
                        $value = $widget->getValueDataType()->parse($value);
                    }
                    $defaults[$widget->getId()] = [$widget->getDataColumnName() => $value];
                }
            });
            // 2) If the widget gets a regular prefill value, discard the default
            $this->getWorkbench()->eventManager()->addListener(OnPrefillChangePropertyEvent::getEventName(), function(OnPrefillChangePropertyEvent $event) use (&$defaults) {
                $widget = $event->getWidget();
                if (array_key_exists($widget->getId(), $defaults) && $event->getPrefillValue() !== '' && $event->getPrefillValue() !== null) {
                    unset ($defaults[$widget->getId()]);
                }
            });
            
            // Do the prefill to trigger the events
            $targetWidget->prefill($mainSheet);
            // If there are $defaults, place the respective values in every empty cell of the data
            // columns used by widgets with default values
            $log .= '   - Found ' . count($defaults) . ' default values.' . PHP_EOL;
            if (! empty($defaults)) {
                $defaultsRow = [];
                foreach ($defaults as $vals) {
                    $defaultsRow = array_merge($defaultsRow, $vals);
                }
                if ($mainSheet->isEmpty()) {
                    $log .= '   - Adding row with defaults to empty data sheet.' . PHP_EOL;
                    $mainSheet->addRow($defaultsRow);
                } else {
                    foreach ($defaultsRow as $colName => $default) {
                        if ($col = $mainSheet->getColumns()->get($colName)) {
                            $log .= '   - Replacing empty values in column "' . $colName . '" with defaults.' . PHP_EOL;
                            foreach ($col->getValues() as $rowNo => $val) {
                                if ($val === '' || $val === null) {
                                    $mainSheet->setCellValue($colName, $rowNo, $default);
                                }
                            }
                        } else {
                            $log .= '   - Adding new column "' . $colName . '" with defaults.' . PHP_EOL;
                            foreach (array_keys($mainSheet->getRows()) as $rowNo) {
                                $mainSheet->setCellValue($colName, $rowNo, $default);
                            }
                        }
                    }
                }
            }
        }
        
        // Fire the event, log it to make it appear in the tracer
        $event = new OnPrefillDataLoadedEvent(
            $targetWidget,
            $mainSheet,
            $this,
            $logSheets,
            $log
        );
        $this->getWorkbench()->getLogger()->debug('Prefill data loaded for object ' . $mainSheet->getMetaObject()->getAliasWithNamespace(), [], $event);
        $this->getWorkbench()->EventManager()->dispatch($event);
        
        // Send back the result
        $result = ResultFactory::createDataResult($task, $mainSheet);
        $result->setMessage($mainSheet->countRows() . ' prefill item(s) found');
        
        return $result;
    }
    
    /**
     * In contrast to ReadData, the default target for a button-action is not always
     * the button itself - if the button opens a widget, the this widget will be
     * automatically treated as target. 
     * 
     * The reason for this behavior is, that the button itself generally does not need
     * any prefill data. It also does not automatically pass a request for prefill data
     * to it's action's widget.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ReadData::getWidgetToReadFor()
     */
    public function getWidgetToReadFor(TaskInterface $task) : ?WidgetInterface
    {
        if (($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->getWidget();
        }
        
        return parent::getWidgetToReadFor($task);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @return WidgetInterface|NULL
     */
    protected function getPrefillTrigger(TaskInterface $task) : ?WidgetInterface
    {
        if ($task->isTriggeredByWidget()) {
            return $task->getWidgetTriggeredBy();
        } else {
            return $this->getWidgetDefinedIn();
        }
    }
    
    /**
     * Returns the action that showed the widget an thus triggered the prefill
     * 
     * @param TaskInterface $task
     * @return ActionInterface|NULL
     */
    protected function getPrefillTriggerAction(TaskInterface $task) : ?ActionInterface
    {
        $trigger = $this->getPrefillTrigger($task);
        if (($trigger instanceof iTriggerAction) && $trigger->hasAction()) {
            $action = $trigger->getAction();
            // If it is an action chain, try to find the trigger action inside the chain
            if ($action instanceof iCallOtherActions) {
                // First see, if the chain finds an exact match
                $step = $action->getActionToStart($task);
                if ($step !== null) {
                    return $step;
                }
                // If not, take the first show-widget-action. This will actually
                // happen moste of the time because the chain will typically include
                // a ShowWidget action and not ReadPrefil explicitly, so the chain
                // itself will not be able to match a task with ReadPrefill with any
                // of its actions.
                $found = [];
                foreach ($action->getActions() as $step) {
                    if ($step instanceof iShowWidget) {
                        $found[] = $step;
                    }
                }
                if (! empty($found)) {
                    if (count($found) === 1) {
                        return $found[0];
                    } else {
                        throw new ActionRuntimeError($this, 'Cannot read prefill data for action in a chain if the chain has multiple ShowWidget actions');
                    }
                }
            } 
            return $action;
        }
        return null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\Traits\iPrefillWidgetTrait::getPrefillWithFilterContext()
     */
    public function getPrefillWithFilterContext(TaskInterface $task = null) : bool
    {
        if ($task && ($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->getPrefillWithFilterContext();
        }
        
        return $this->getPrefillWithFilterContextViaTrait();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\Traits\iPrefillWidgetTrait::getPrefillWithInputData()
     */
    public function getPrefillWithInputData(TaskInterface $task = null) : bool
    {
        if ($task && ($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->getPrefillWithInputData();
        }
        
        return $this->getPrefillWithInputDataViaTrait();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\Traits\iPrefillWidgetTrait::getPrefillWithPrefillData()
     */
    public function getPrefillWithPrefillData(TaskInterface $task = null) : bool
    {
        if ($task && ($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->getPrefillWithPrefillData();
        }
        
        return $this->getPrefillWithPrefillDataViaTrait();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\Traits\iPrefillWidgetTrait::getPrefillWithDefaults()
     */
    public function getPrefillWithDefaults(TaskInterface $task = null) : ?bool
    {
        if ($task && ($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->getPrefillWithDefaults() ?? true;
        }
        
        return $this->getPrefillWithDefaultsViaTrait();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\Traits\iPrefillWidgetTrait::getPrefillDataPreset()
     */
    public function getPrefillDataPreset(TaskInterface $task = null) : ?DataSheetInterface
    {
        if ($task && ($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->getPrefillDataPreset();
        }
        
        return $this->getPrefillDataPresetViaTrait();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\Traits\iPrefillWidgetTrait::hasPrefillDataPreset()
     */
    public function hasPrefillDataPreset(TaskInterface $task = null) : bool
    {
        if ($task && ($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->hasPrefillDataPreset();
        }
        
        return $this->hasPrefillDataPresetViaTrait();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\Traits\iPrefillWidgetTrait::getPrefillDataSheet()
     */
    public function getPrefillDataSheet(TaskInterface $task) : DataSheetInterface
    {
        if ($task && ($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->getPrefillDataSheet($task);
        }
        
        return $this->getPrefillDataSheetViaTrait($task);
    }
    
    /**
     * A specific prefill widget is not neccessarily required - a page is enough.
     * 
     * @see \exface\Core\CommonLogic\AbstractAction::isTriggerWidgetRequired()
     */
    public function isTriggerWidgetRequired() : ?bool
    {
        return true;
    }
}