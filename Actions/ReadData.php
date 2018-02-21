<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionCallingWidgetNotSpecifiedError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\CommonLogic\Tasks\TaskResultData;

class ReadData extends AbstractAction implements iReadData
{

    private $affected_rows = 0;

    private $update_filter_context = true;

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        $this->setAffectedRows($data_sheet->removeRows()->dataRead());
        
        // Replace the filter conditions in the current window context by the ones in this data sheet
        // It is important to do it after the data had been read, because otherwise the newly set
        // context filters would affect the result of the read operation (context filters are automatically
        // applied to the query, each time, data is fetched)
        if ($this->getUpdateFilterContext()) {
            $this->updateFilterContext($data_sheet);
        }
        
        $result = new TaskResultData($task, $data_sheet);
        $result->setMessage($this->getAffectedRows() . ' entries read');
    }
    
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

    protected function getAffectedRows()
    {
        return $this->affected_rows;
    }

    protected function setAffectedRows($value)
    {
        if ($value == 0) {
            $this->setUndoable(false);
        }
        $this->affected_rows = $value;
    }

    public function getUpdateFilterContext()
    {
        return $this->update_filter_context;
    }

    public function setUpdateFilterContext($value)
    {
        $this->update_filter_context = $value;
        return $this;
    }
}
?>