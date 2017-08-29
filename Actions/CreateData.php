<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;

class CreateData extends SaveData implements iCreateData
{

    protected function perform()
    {
        $data_sheet = $this->getInputDataSheet();
        $this->setAffectedRows($data_sheet->dataCreate(true, $this->getTransaction()));
        $this->setUndoDataSheet($data_sheet);
        $this->setResultDataSheet($data_sheet);
        $this->setResult('');
        $this->setResultMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CREATEDATA.RESULT', array(
            '%number%' => $this->getAffectedRows()
        ), $this->getAffectedRows()));
    }

    public function undo(DataTransactionInterface $transaction = null)
    {
        if (! $data_sheet = $this->getUndoDataSheet()) {
            throw new ActionUndoFailedError($this, 'Cannot undo action "' . $this->getAliasWithNamespace() . '": Failed to load history for this action!', '6T5DLGN');
        }
        $data_sheet->dataDelete($transaction ? $transaction : $this->getTransaction());
        return $data_sheet;
    }
}
?>