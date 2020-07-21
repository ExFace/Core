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
 * Technically, this action passes it's input data to prepareDataSheetToPrefill() of
 * it's target widget, reads the resulting sheet and returns it.
 * 
 * @author Andrej Kabachnik
 *
 */
class ReadPrefill extends ReadData
{

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
        
        if ($data_sheet->isEmpty()) {
            return ResultFactory::createDataResult($task, $data_sheet);
        } else {
            if ($data_sheet->hasUidColumn(true)) {
                $data_sheet->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
            } /*elseif ($data_sheet->getFilters()->isEmpty() === false) {
                return ResultFactory::createDataResult($task, $data_sheet->removeRows());
            }*/
        }
        
        // Let widgets modify the data sheet if neccessary
        if ($targetWidget = $this->getWidgetToReadFor($task)) {
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
        if ($task->isTriggeredByWidget()) {
            $trigger = $task->getWidgetTriggeredBy();
        } else {
            $trigger = $this->getWidgetDefinedIn();
        }
        
        if (($trigger instanceof iTriggerAction) && $trigger->getAction() instanceof iShowWidget) {
            return $trigger->getAction()->getWidget();
        }
        
        return parent::getWidgetToReadFor($task);
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