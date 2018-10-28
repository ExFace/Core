<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\WidgetInterface;

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
        if (! $this->checkPermissions($task)) {
            // TODO Throw exception!
        }
        
        $data_sheet = $this->getInputDataSheet($task);
        
        if ($data_sheet->isEmpty()) {
            return $data_sheet;
        } else {
            if ($data_sheet->hasUidColumn(true)) {
                $data_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
            } else {
                return $data_sheet->removeRows();
            }
        }
        
        if ($targetWidget = $this->getWidgetToReadFor($task)) {
            $data_sheet = $targetWidget->prepareDataSheetToPrefill($data_sheet);
        }
        
        $affected_rows = $data_sheet->dataRead();
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
}