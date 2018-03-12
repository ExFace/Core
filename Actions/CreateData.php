<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\TaskResultFactory;
use exface\Core\Interfaces\Tasks\TaskResultInterface;

class CreateData extends SaveData implements iCreateData
{
    private $ingnore_related_objects_in_input_data = false;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        $affected_rows = 0;
        
        if ($this->getIgnoreRelatedObjectsInInputData()){
            $clean_sheet = $data_sheet->copy();
            foreach ($clean_sheet->getColumns() as $col){
                if ($col->getExpressionObj()->isMetaAttribute() && ! $col->getAttribute()->getRelationPath()->isEmpty()){
                    $clean_sheet->getColumns()->remove($col);
                }
            }
            $affected_rows += $clean_sheet->dataCreate(true, $transaction);
            $data_sheet->merge($clean_sheet);
        } else {
            $affected_rows += $data_sheet->dataCreate(true, $transaction);
        }
        
        // FIXME #api-v4 implement undo
        // $this->setUndoDataSheet($data_sheet);
        
        $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CREATEDATA.RESULT', ['%number%' => $affected_rows], $affected_rows);
        return TaskResultFactory::createDataResult($task, $data_sheet, $message);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::undo()
     */
    public function undo(DataTransactionInterface $transaction)
    {
        if (! $data_sheet = $this->getUndoDataSheet()) {
            throw new ActionUndoFailedError($this, 'Cannot undo action "' . $this->getAliasWithNamespace() . '": Failed to load history for this action!', '6T5DLGN');
        }
        $data_sheet->dataDelete($transaction);
        return $data_sheet;
    }
    
    /**
     * 
     * @return bool
     */
    public function getIgnoreRelatedObjectsInInputData() : bool
    {
        return $this->ingnore_related_objects_in_input_data;
    }
    
    /**
     * Strips off all columns with relations from the input data sheet before creating data.
     * 
     * This is usefull if you have related columns in your data for some reason,
     * but do not want to update them when creating new data rows.
     * 
     * Note, that related columns will only be ignored in the create operation.
     * The result data sheet will still contain them!
     * 
     * @uxon-property ignore_related_objects_in_input_data
     * @uxon-type boolean
     * 
     * @param bool $true_or_false
     * @return \exface\Core\Actions\CreateData
     */
    public function setIgnoreRelatedObjectsInInputData($true_or_false)
    {
        $this->ingnore_related_objects_in_input_data = BooleanDataType::cast($true_or_false);
        return $this;
    }
}
?>