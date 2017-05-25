<?php

namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iUpdateData;
use exface\Core\Interfaces\Actions\iCanBeUndone;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;

class UpdateData extends SaveData implements iUpdateData, iCanBeUndone
{

    private $use_context_filters = false;

    protected function perform()
    {
        $data_sheet = $this->getInputDataSheet();
        if (! $data_sheet->getUidColumn()) {
            foreach ($this->getApp()
                ->getWorkbench()
                ->context()
                ->getScopeWindow()
                ->getFilterContext()
                ->getConditions($data_sheet->getMetaObject()) as $cond) {
                $data_sheet->getFilters()->addCondition($cond);
            }
        }
        
        if ($this->getUseContextFilters()) {
            if ($conditions = $this->getWorkbench()
                ->context()
                ->getScopeWindow()
                ->getFilterContext()
                ->getConditions($data_sheet->getMetaObject())) {
                foreach ($conditions as $condition) {
                    $data_sheet->getFilters()->addCondition($condition);
                }
            }
        }
        
        // Create a backup of the current data for this data sheet (it can be used for undo operations later)
        if ($data_sheet->countRows() && $data_sheet->getUidColumn()) {
            $backup = $data_sheet->copy();
            $backup->addFilterFromColumnValues($backup->getUidColumn());
            $backup->removeRows()->dataRead();
            $this->setUndoDataSheet($backup);
        } else {
            $this->setUndoable(false);
        }
        
        $this->setAffectedRows($data_sheet->dataUpdate(false, $this->getTransaction()));
        $this->setResult('');
        $this->setResultDataSheet($data_sheet);
        $this->setResultMessage($this->getWorkbench()
            ->getCoreApp()
            ->getTranslator()
            ->translate('ACTION.UPDATEDATA.RESULT', array(
            '%number%' => $this->getAffectedRows()
        ), $this->getAffectedRows()));
    }

    public function undo(DataTransactionInterface $transaction = null)
    {
        if (! $data_sheet = $this->getUndoDataSheet()) {
            throw new ActionUndoFailedError($this, 'Cannot undo action "' . $this->getAlias() . '": Failed to load history for this action!', '6T5DLGN');
        }
        $data_sheet->dataUpdate($transaction ? $transaction : $this->getTransaction());
        return $data_sheet;
    }

    public function getUseContextFilters()
    {
        return $this->use_context_filters;
    }

    public function setUseContextFilters($value)
    {
        $this->use_context_filters = $value;
        return $this;
    }
}
?>