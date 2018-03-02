<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iDeleteData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Tasks\TaskResultMessage;
use exface\Core\Interfaces\Tasks\TaskResultInterface;

class DeleteObject extends AbstractAction implements iDeleteData
{

    private $affected_rows = 0;

    protected function init()
    {
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIcon(Icons::TRASH_O);
    }

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {
        $input_data = $this->getInputDataSheet($task);
        /* @var $data_sheet \exface\Core\Interfaces\DataSheets\DataSheetInterface */
        $obj = $input_data->getMetaObject();
        $ds = DataSheetFactory::createFromObject($obj);
        $instances = array();
        foreach ($input_data->getRows() as $row) {
            $instances[] = $row[$obj->getUidAttributeAlias()];
        }
        
        if (count($instances) > 0) {
            $ds->addFilterInFromString($obj->getUidAttributeAlias(), $instances);
            $this->setAffectedRows($this->getAffectedRows() + $ds->dataDelete($this->getTransaction()));
        }
        
        $result = new TaskResultMessage($task);
        $result->setMessage($this->translate('RESULT', ['%number%' => $this->getAffectedRows()], $this->getAffectedRows()));
        return $result;
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