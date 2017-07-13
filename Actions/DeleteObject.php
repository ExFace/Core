<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iDeleteData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;

class DeleteObject extends AbstractAction implements iDeleteData
{

    private $affected_rows = 0;

    protected function init()
    {
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIconName(Icons::TRASH_O);
    }

    protected function perform()
    {
        /* @var $data_sheet \exface\Core\Interfaces\DataSheets\DataSheetInterface */
        $obj = $this->getInputDataSheet()->getMetaObject();
        $ds = $this->getApp()->getWorkbench()->data()->createDataSheet($obj);
        $instances = array();
        foreach ($this->getInputDataSheet()->getRows() as $row) {
            $instances[] = $row[$obj->getUidAlias()];
        }
        
        if (count($instances) > 0) {
            $ds->addFilterInFromString($obj->getUidAlias(), $instances);
            $this->setAffectedRows($this->getAffectedRows() + $ds->dataDelete($this->getTransaction()));
        }
        $this->setResult('');
        $this->setResultMessage($this->translate('RESULT', array(
            '%number%' => $this->getAffectedRows()
        ), $this->getAffectedRows()));
        // IDEA Currently the delete action returns an empty data sheet with a filter, but
        // no columns. Perhaps it is more elegant to return the input data sheet with a filter
        // and not data, but the columns still being there...
        $this->setResultDataSheet($ds);
    }

    protected function getAffectedRows()
    {
        return $this->affected_rows;
    }

    protected function setAffectedRows($value)
    {
        $this->affected_rows = $value;
    }
}
?>