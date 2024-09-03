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
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iHaveColumns;

/**
 * Reads data required for its widget or explicitly requestend in the config of the action.
 * 
 * This action reads fresh data from the data source of its object.
 * 
 * There are multiple scenarios where this action can be used:
 * 
 * - Reading data for a lazy loading widget. This is done automatically by all lazy loading widgets. In
 * this case, no explicit configuration for the action is required.
 * - Reading data for an explicitly defined widget. This is similar to the automatics of lazy loading
 * widgets, but you set the `widget_to_read_for` explicitly. 
 * - Reading arbitrary data - e.g. in `ActionChains`. In this case, the action will read fresh data
 * for the data sheet it receives as input. You can also explicitly define `columns` to read in the
 * configuration of the action.
 * 
 * @author Andrej Kabachnik
 *
 */
class ReadData extends AbstractAction implements iReadData
{

    private $affected_rows = 0;

    private $update_filter_context = null;
    
    private $widgetToReadFor = null;
    
    private $columns = null;

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
        
        // If reading for a specific widget, ask the widget, what columns it needs.
        // Note: there may also be cases, where data is read for another object - e.g. if the ReadData
        // action is part of an action chain. In this case, simply read the columns there are.
        if ($dataWidget !== null && ($dataWidget instanceof iSupportLazyLoading) && $this === $dataWidget->getLazyLoadingAction()) {
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
            $widget = $task->getPageTriggeredOn()->getWidget($this->widgetToReadFor);
        } else {
            if ($task->isTriggeredByWidget()) {
                $trigger = $task->getWidgetTriggeredBy();
            } elseif ($this->isDefinedInWidget()) {
                $trigger = $this->getWidgetDefinedIn();
            }
            
            if ($trigger !== null) {
                if ($trigger instanceof iUseInputWidget) {
                    $widget = $trigger->getInputWidget();
                } else {
                    $widget = $trigger;
                }
            }
        }
        
        $widget = $this->applyCustomWidgetProperties($task, $widget);
        
        return $widget;
    }
    
    /**
     * 
     * @return UxonObject|NULL
     */
    protected function getCustomColumnsUxon() : ?UxonObject
    {
        return $this->customColumnsUxon;
    }
    
    /**
     * Explicitly specify the column to be read
     * 
     * By default this action will read the columns of its input widget. However, you can also define your
     * own set of columns here explicitly. In this case, only the filters will be inherited from the input
     * widget.
     * 
     * @uxon-property columns
     * @uxon-type \exface\Core\Widgets\DataColumn[]|\exface\Core\Widgets\DataColumnGroup[]
     * @uxon-template [{"attribute_alias": ""}]
     * 
     * @param UxonObject $value
     * @return ReadData
     */
    protected function setColumns(UxonObject $value) : ReadData
    {
        $this->customColumnsUxon = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasCustomColumns() : bool
    {
        return $this->customColumnsUxon !== null;
    }
    
    /**
     * Modifies the given base widget adding `columns` and other properties explicitly defined in this action
     * 
     * @return WidgetInterface|NULL
     */
    protected function applyCustomWidgetProperties(TaskInterface $task, WidgetInterface $baseWidget = null) : ?WidgetInterface
    {
        $uxonForCols = $this->getCustomColumnsUxon();
        if ($uxonForCols === null) {
            return $baseWidget;
        }
        
        // Use the input widget if the object of the exported data is the same as 
        // that of the widget or inherits from it (= if we know, that the data sheet 
        // can read all the attributes required).
        // That is, is we have a `LOCATION` and a `FACTORY`, that extends `LOCATION`,
        // we can prefill a LOCATION-widget with FACTORY-data, but not the other way
        // around.
        if ($baseWidget !== null && ($baseWidget instanceof iHaveColumns) && $this->getMetaObject()->is($baseWidget->getMetaObject())) {
            $page = $baseWidget->getPage();
            $uxon = $baseWidget->exportUxonObject();
            // Remove any explicitly set widget id because we are going to instantiate
            // a new widget, which will fail with the same explicit id
            $uxon->unsetProperty('id');
            $uxon->setProperty('columns', $uxonForCols);
        } else {
            $page = $task->getPageTriggeredOn();
            $uxon = new UxonObject([
                'widget_type' => 'Data',
                'object_alias' => $this->getMetaObject()->getAliasWithNamespace(),
                'columns' => $uxonForCols->toArray()
            ]);
        }
        
        return WidgetFactory::createFromUxon($page, $uxon);
    }
}