<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Exceptions\Actions\ActionUndoFailedError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Actions\ActionRuntimeError;

/**
 * Creates data in the data source(s).
 * 
 * Executes create-operations in all data sources holding data from the input data sheet.
 * This is very similar to saving data via `SaveData`, but it forces a create operation.
 * 
 * If you just want to save the main object of the data sheet, set `ignore_related_objects_in_input_data`
 * to `true`. This will ignore any data columns of related objects in the create operation, but
 * will keep them in the result data as-is.
 * 
 * @author Andrej Kabachnik
 *
 */
class CreateData extends SaveData implements iCreateData
{
    private $ingnore_related_objects_in_input_data = false;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
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
            if ($data_sheet->hasUidColumn(false)) {
                $data_sheet->getUidColumn()->setValues($clean_sheet->getUidColumn()->getValues(false));
                $data_sheet->merge($clean_sheet);
            } else {
                throw new ActionRuntimeError($this, 'CreateData actions without UID columns in their input currently cannot use `ignore_related_objects_in_input_data`!');
            }
        } else {
            $affected_rows += $data_sheet->dataCreate(true, $transaction);
        }
        
        $this->setUndoDataSheet($data_sheet);
        
        $message = $this->getResultMessageText() ?? $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CREATEDATA.RESULT', ['%number%' => $affected_rows], $affected_rows);
        $result = ResultFactory::createDataResult($task, $data_sheet, $message);
        if ($affected_rows > 0) {
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
     * Removes all columns with relations from the input data sheet before creating data and adds them back afterwards.
     * 
     * This is usefull if you have related columns in your data for some reason,
     * but do not want to update them when creating new data rows (e.g. if you need
     * the data in subsequent actions in an `ActionChain`).
     * 
     * **Note**, that related columns will only be ignored in the create operation.
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