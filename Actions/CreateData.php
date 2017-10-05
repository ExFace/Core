<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\DataTypes\BooleanDataType;

class CreateData extends SaveData implements iCreateData
{
    private $ingnore_related_objects_in_input_data = false;

    protected function perform()
    {
        $data_sheet = $this->getInputDataSheet();
        
        if ($this->getIgnoreRelatedObjectsInInputData()){
            foreach ($data_sheet->getColumns() as $col){
                if ($col->getExpressionObj()->isMetaAttribute() && ! $col->getAttribute()->getRelationPath()->isEmpty()){
                    $data_sheet->getColumns()->remove($col);
                }
            }
        }
        
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
    
    public function getIgnoreRelatedObjectsInInputData()
    {
        return $this->ingnore_related_objects_in_input_data;
    }
    
    /**
     * Strips off all columns with relations from the input data sheet before creating - if set to TRUE.
     * 
     * This is usefull if you have related columns in your data for some reason,
     * but do not want to update them when creating new data rows.
     * 
     * @uxon-property ignore_related_objects_in_input_data
     * @uxon-type boolean
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Actions\CreateData
     */
    public function setIgnoreRelatedObjectsInInputData($true_or_false)
    {
        $this->ingnore_related_objects_in_input_data = BooleanDataType::parse($true_or_false);
        return $this;
    }
}
?>