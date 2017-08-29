<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Actions\iCanBeUndone;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\CommonLogic\Constants\Icons;

class SaveData extends AbstractAction implements iModifyData, iCanBeUndone
{

    private $affected_rows = 0;

    private $undo_data_sheet = null;

    function init()
    {
        $this->setIconName(Icons::CHECK);
        $this->setInputRowsMin(0);
        $this->setInputRowsMax(null);
    }

    protected function perform()
    {
        $data_sheet = $this->getInputDataSheet();
        $this->setAffectedRows($data_sheet->dataSave($this->getTransaction()));
        $this->setResultDataSheet($data_sheet);
        $this->setResult('');
        $this->setResultMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SAVEDATA.RESULT', array(
            '%number%' => $this->getAffectedRows()
        ), $this->getAffectedRows()));
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

    /**
     *
     * @return DataSheetInterface
     */
    public function getUndoDataSheet()
    {
        return $this->undo_data_sheet;
    }

    public function setUndoDataSheet(DataSheetInterface $data_sheet)
    {
        $this->undo_data_sheet = $data_sheet;
    }

    public function getUndoDataSerializable()
    {
        if ($this->getUndoDataSheet()) {
            return $this->getUndoDataSheet()->exportUxonObject();
        } else {
            return new UxonObject();
        }
    }

    public function setUndoData(\stdClass $uxon_object)
    {
        $exface = $this->getApp()->getWorkbench();
        $this->undo_data_sheet = DataSheetFactory::createFromStdClass($exface, $uxon_object);
    }

    public function undo(DataTransactionInterface $transaction = null)
    {
        throw new ActionUndoFailedError($this, 'Undo functionality not implemented yet for action "' . $this->getAlias() . '"!', '6T5DS00');
    }
}
?>