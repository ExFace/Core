<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\CommonLogic\Tasks\TaskResultData;
use exface\Core\Exceptions\Actions\ActionCallingWidgetNotSpecifiedError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class ReadData extends AbstractAction implements iReadData
{

    private $affected_rows = 0;

    private $update_filter_context = true;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {
        if (! $this->checkPermissions($task)) {
            // TODO Throw exception!
        }
        
        $data_sheet = $this->getInputDataSheet($task);
        $data_sheet->removeRows();
        if ($task->isTriggeredByWidget()) {
            $data_sheet = $task->getWidgetTriggeredBy()->prepareDataSheetToRead($data_sheet);
        }
        $affected_rows = $data_sheet->dataRead();
        
        // Replace the filter conditions in the current window context by the ones in this data sheet
        // It is important to do it after the data had been read, because otherwise the newly set
        // context filters would affect the result of the read operation (context filters are automatically
        // applied to the query, each time, data is fetched)
        if ($this->getUpdateFilterContext()) {
            $this->updateFilterContext($data_sheet);
        }
        
        $result = new TaskResultData($task, $data_sheet);
        $result->setMessage($affected_rows . ' entries read');
        
        return $result;
    }
    
    protected function checkPermissions(TaskInterface $task) : bool
    {
        if (! $this->isDefinedInWidget() && ! $task->isTriggeredByWidget()) {
            throw new ActionCallingWidgetNotSpecifiedError($this, 'Security violaion! Cannot read data without a target widget in action "' . $this->getAliasWithNamespace() . '"!', '6T5DOSV');
        }
        return true;
    }
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @return \exface\Core\Actions\ReadData
     */
    protected function updateFilterContext(DataSheetInterface $data_sheet)
    {
        $context = $this->getApp()->getWorkbench()->context()->getScopeWindow()->getFilterContext();
        $context->removeAllConditions();
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
    public function getUpdateFilterContext()
    {
        return $this->update_filter_context;
    }

    /**
     * 
     * @param bool $value
     * @return \exface\Core\Actions\ReadData
     */
    public function setUpdateFilterContext($value)
    {
        $this->update_filter_context = $value;
        return $this;
    }
}
?>