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
        // Get the prefill data from the request. 
        // TODO The logic here should ideally be the same as in ShowWidget::prefillWidget(), but at the
        // moment, there is no way to use the very same code. Perhaps trait could help...
        $data_sheet = null;
        try {
            $data_sheet = $this->getInputDataSheet($task);
        } catch (ActionInputMissingError $e) {
            // ignore missing data - continue with the next if
        }
        if ($data_sheet === null || $data_sheet->isBlank() && $task->hasPrefillData()) {
            $data_sheet = $task->getPrefillData();
        } 
        
        // We don't need the total row count for prefills.
        $data_sheet->setAutoCount(false);
        
        // IDEA are there other ways to load more data, than use UID-filters?
        $canLoadMoreData = $data_sheet->hasUidColumn(true);
        
        $targetWidget = $this->getWidgetToReadFor($task);
        
        if ($data_sheet->isEmpty()) {
            $data_sheet = $this->getPrefillDataFromFilterContext($targetWidget, $data_sheet, $task);
            return ResultFactory::createDataResult($task, $data_sheet);
        } else {
            if ($data_sheet->hasUidColumn(true)) {
                $data_sheet->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
            } /*elseif ($data_sheet->getFilters()->isEmpty() === false) {
                return ResultFactory::createDataResult($task, $data_sheet->removeRows());
            }*/
        }
        
        // Let widgets modify the data sheet if neccessary
        if ($targetWidget) {
            $data_sheet = $targetWidget->prepareDataSheetToPrefill($data_sheet);
        }
        
        // Reed data if it is not fresh
        if ($canLoadMoreData === true && $data_sheet->isFresh() === false) {
            $affected_rows = $data_sheet->dataRead();
        }
        
        $result = ResultFactory::createDataResult($task, $data_sheet);
        $result->setMessage($affected_rows . ' entries read');
        
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
    
    protected function getPrefillTrigger(TaskInterface $task) : ?WidgetInterface
    {
        if ($task->isTriggeredByWidget()) {
            return $task->getWidgetTriggeredBy();
        } else {
            return $this->getWidgetDefinedIn();
        }
    }
    
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
    public function getPrefillDataSheet(TaskInterface $task = null) : DataSheetInterface
    {
        if ($task && ($action = $this->getPrefillTriggerAction($task)) instanceof iShowWidget) {
            return $action->getPrefillDataSheet();
        }
        
        return $this->getPrefillDataSheetViaTrait();
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