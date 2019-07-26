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
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Tasks\ResultInterface;

class SaveData extends AbstractAction implements iModifyData, iCanBeUndone
{

    private $affected_rows = 0;

    private $undo_data_sheet = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::CHECK);
        $this->setInputRowsMin(0);
        $this->setInputRowsMax(null);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        $affected_rows = $data_sheet->dataSave($transaction);
        
        $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SAVEDATA.RESULT', ['%number%' => $affected_rows], $affected_rows);
        $result = ResultFactory::createDataResult($task, $data_sheet, $message);
        
        if ($affected_rows > 0) {
            $result->setDataModified(true);
        }
        
        return $result;
    }

    /**
     *
     * @return DataSheetInterface
     */
    protected function getUndoDataSheet()
    {
        return $this->undo_data_sheet;
    }

    /**
     * 
     * @param DataSheetInterface $data_sheet
     */
    protected function setUndoDataSheet(DataSheetInterface $data_sheet)
    {
        $this->undo_data_sheet = $data_sheet;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeUndone::getUndoDataUxon()
     */
    public function getUndoDataUxon()
    {
        if ($this->getUndoDataSheet()) {
            return $this->getUndoDataSheet()->exportUxonObject();
        } else {
            return new UxonObject();
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeUndone::setUndoData()
     */
    public function setUndoData(UxonObject $uxon_object) : iCanBeUndone
    {
        $exface = $this->getApp()->getWorkbench();
        $this->undo_data_sheet = DataSheetFactory::createFromUxon($exface, $uxon_object);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeUndone::undo()
     */
    public function undo(DataTransactionInterface $transaction) : DataSheetInterface
    {
        throw new ActionUndoFailedError($this, 'Undo functionality not implemented yet for action "' . $this->getAlias() . '"!', '6T5DS00');
    }
}
?>