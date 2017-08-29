<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionCallingWidgetNotSpecifiedError;

class ReadData extends AbstractAction implements iReadData
{

    private $affected_rows = 0;

    private $update_filter_context = true;

    protected function perform()
    {
        $data_sheet = $this->getInputDataSheet();
        $this->setAffectedRows($data_sheet->removeRows()->dataRead());
        
        // Replace the filter conditions in the current window context by the ones in this data sheet
        // It is important to do it after the data had been read, because otherwise the newly set
        // context filters would affect the result of the read operation (context filters are automatically
        // applied to the query, each time, data is fetched)
        if ($this->getUpdateFilterContext()) {
            $this->getApp()->getWorkbench()->context()->getScopeWindow()->getFilterContext()->removeAllConditions();
            foreach ($data_sheet->getFilters()->getConditions() as $condition) {
                $this->getApp()->getWorkbench()->context()->getScopeWindow()->getFilterContext()->addCondition($condition);
            }
        }
        
        $this->setResultDataSheet($data_sheet);
        $this->setResultMessage($this->getAffectedRows() . ' entries read');
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

    public function getResultOutput()
    {
        if (! $this->getCalledByWidget()) {
            throw new ActionCallingWidgetNotSpecifiedError($this, 'Security violaion! Cannot read data without a target widget in action "' . $this->getAliasWithNamespace() . '"!', '6T5DOSV');
        }
        $elem = $this->getApp()->getWorkbench()->ui()->getTemplate()->getElement($this->getCalledByWidget());
        $output = $elem->prepareData($this->getResultDataSheet());
        return $this->getApp()->getWorkbench()->ui()->getTemplate()->encodeData($output);
    }
}
?>