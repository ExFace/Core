<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iUpdateData;
use exface\Core\Interfaces\Actions\iCanBeUndone;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\iCanValidate;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Actions\ActionRuntimeError;

class UpdateData extends SaveData implements iUpdateData, iCanBeUndone
{
    private $use_context_filters = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        
        // Add filters from context if applicable
        if ($this->getUseContextFilters() === true || ($this->getUseContextFilters() === null && ! $data_sheet->hasUidColumn(true) && $data_sheet->getFilters()->isEmpty(true))) {
            $conditions = $this->getWorkbench()->getContext()->getScopeWindow()->getFilterContext()->getConditions($data_sheet->getMetaObject());
            if (is_array($conditions) || ! empty($conditions)) {
                foreach ($conditions as $condition) {
                    $data_sheet->getFilters()->addCondition($condition);
                }
            }
        }
        
        // Create a backup of the current data for this data sheet (it can be used for undo operations later)
        /* TODO #undo-action
        if ($data_sheet->countRows() && $data_sheet->getUidColumn()) {
            $backup = $data_sheet->copy();
            $backup->getFilters()->addConditionFromColumnValues($backup->getUidColumn());
            $backup->removeRows()->dataRead();
            $this->setUndoDataSheet($backup);
            $undoable = true;
        } else {
            $undoable = false;
        }*/
        $undoable = false;
        
        try {
            $affectedRows = $data_sheet->dataUpdate(false, $transaction);
        } catch (\Throwable $e) {
            throw new ActionRuntimeError($this, 'Cannot update data of object ' . $this->getMetaObject()->__toString() . '. ' . $e->getMessage(), null, $e);
        }
        
        if (null !== $message = $this->getResultMessageText()) {
            $message =  str_replace('%number%', $affectedRows, $message);
        } else {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.UPDATEDATA.RESULT', ['%number%' => $affectedRows], $affectedRows);
        }
        $result = ResultFactory::createDataResult($task, $data_sheet, $message);
        $result->setUndoable($undoable);
        if ($affectedRows > 0) {
            $result->setDataModified(true);
        }
        
        return $result;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::undo()
     */
    public function undo(DataTransactionInterface $transaction) : DataSheetInterface
    {
        if (! $data_sheet = $this->getUndoDataSheet()) {
            throw new ActionUndoFailedError($this, 'Cannot undo action "' . $this->getAlias() . '": Failed to load history for this action!', '6T5DLGN');
        }
        $data_sheet->dataUpdate($transaction);
        return $data_sheet;
    }

    /**
     * Returns TRUE if filters from the filter context must be added to every update query
     * within this action.
     * 
     * @return bool
     */
    public function getUseContextFilters() : ?bool
    {
        return $this->use_context_filters;
    }

    /**
     * Set to TRUE to force adding filters from the filter context to all update queries - FALSE by default.
     * 
     * By default the filter context is only used as a filter when updating if the
     * data to be updated neither has UIDs nor explicitly set filters, meaning, that 
     * all data would get updated.
     * In this case, it is important to doublecheck the context as we would otherwise
     * update data that the user does not see in the current context. With a UID column
     * this cannot happen, because only rows explicitly selected by the user could make
     * it in such a data sheet.
     * 
     * @uxon-property use_context_filters
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return \exface\Core\Actions\UpdateData
     */
    public function setUseContextFilters(bool $value) : UpdateData
    {
        $this->use_context_filters = $value;
        return $this;
    }
}
?>