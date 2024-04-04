<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Exceptions\Actions\ActionRuntimeError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class ReadData extends AbstractAction implements iReadData
{

    private $affected_rows = 0;

    private $update_filter_context = null;
    
    private $widgetToReadFor = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        $data_sheet->removeRows();
        $dataWidget = $this->getWidgetToReadFor($task);
        
        // If reading for a specific widget and that widget is based on the object of the data sheet,
        // ask the widget, what columns it needs.
        // Note: there may also be cases, where data is read for another object - e.g. if the ReadData
        // action is part of an action chain. In this case, simply read the columns there are.
        if ($dataWidget !== null && $dataWidget->getMetaObject()->is($data_sheet->getMetaObject())) {
            $data_sheet = $dataWidget->prepareDataSheetToRead($data_sheet);
        }
        
        if ($data_sheet->getColumns()->isEmpty(false)) {
            throw new ActionRuntimeError($this, 'Cannot read data for ' . $data_sheet->getMetaObject() . ' - no columns to read specified!');
        }
        
        // Read from the data source
        $affected_rows = $data_sheet->dataRead();
        
        // Replace the filter conditions in the current window context by the ones in this data sheet
        // It is important to do it after the data had been read, because otherwise the newly set
        // context filters would affect the result of the read operation (context filters are automatically
        // applied to the query, each time, data is fetched)
        if ($this->getUpdateFilterContext($data_sheet)) {
            $this->updateFilterContext($data_sheet);
        }
        
        $result = ResultFactory::createDataResult($task, $data_sheet);
        if (null !== $message = $this->getResultMessageText()) {
            $message =  str_replace('%number%', $affected_rows, $message);
        } else {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.READDATA.RESULT', ['%number%' => $affected_rows], $affected_rows);
        }
        $result->setMessage($message);
        
        return $result;
    }
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @return \exface\Core\Actions\ReadData
     */
    protected function updateFilterContext(DataSheetInterface $data_sheet)
    {
        $context = $this->getApp()->getWorkbench()->getContext()->getScopeWindow()->getFilterContext();
        $context->removeConditionsForObject($data_sheet->getMetaObject());
        foreach ($data_sheet->getFilters()->getConditions() as $condition) {
            if (! $condition->isEmpty()){
                $context->addCondition($condition);
            } 
        }
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getUpdateFilterContext(DataSheetInterface $data) : bool
    {
        return $this->update_filter_context ?? ! $data->hasAggregations();
    }

    /**
     * Set to TRUE/FALSE to force passing the filters of this action to the filter context (or not).
     * 
     * By default, any explicit read-operation (not autosuggest or so) without
     * aggregation will update the filter context
     * 
     * @uxon-property update_filter_context
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return \exface\Core\Actions\ReadData
     */
    public function setUpdateFilterContext(bool $value) : ReadData
    {
        $this->update_filter_context = $value;
        return $this;
    }
    
    /**
     * The id of the widget to read the data for.
     * 
     * If not set, the input widget, of the trigger of the task will be used.
     * 
     * Setting a custom target widget allows to create buttons, that load/refresh data 
     * in a specific widget.
     * 
     * @uxon-property widget_id_to_read_for
     * @uxon-type string
     * 
     * @param string $value
     * @return ReadData
     */
    public function setWidgetIdToReadFor(string $value) : ReadData
    {
        $this->widgetToReadFor = $value;
        return $this;
    }
    
    /**
     * Returns the widget for which the data is to be read.
     * 
     * @param TaskInterface $task
     * 
     * @return WidgetInterface|NULL
     */
    public function getWidgetToReadFor(TaskInterface $task) : ?WidgetInterface
    {
        if ($this->widgetToReadFor !== null) {
            return $task->getPageTriggeredOn()->getWidget($this->widgetToReadFor);
        }
        
        if ($task->isTriggeredByWidget()) {
            $trigger = $task->getWidgetTriggeredBy();
        } elseif ($this->isDefinedInWidget()) {
            $trigger = $this->getWidgetDefinedIn();
        }
        
        if ($trigger !== null) {
            if ($trigger instanceof iUseInputWidget) {
                return $trigger->getInputWidget();
            } else {
                return $trigger;
            }
        }
        
        return null;
    }
}
?>