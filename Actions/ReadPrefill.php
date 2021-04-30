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
            } else {
                $log .= '- Refresh is not required or not possible' . PHP_EOL;
            }
        }
        
        if ($mainSheet === null) {
            $mainSheet = DataSheetFactory::createFromObject($this->getMetaObject());
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
     * 
     * @param TaskInterface $task
     * @return ActionInterface|NULL
     */
    protected function getPrefillTriggerAction(TaskInterface $task) : ?ActionInterface
    {
        $trigger = $this->getPrefillTrigger($task);
        if (($trigger instanceof iTriggerAction) && $trigger->hasAction()) {
            return $trigger->getAction();
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