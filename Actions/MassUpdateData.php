<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Tasks\ResultData;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use function Sabre\Event\Loop\instance;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class MassUpdateData extends UpdateData
{
    private $flattenMultiRowInput = true;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\SaveData::init()
     */
    protected function init()
    {
        parent::init();
        $this->setUseContextFilters(true);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\UpdateData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        
        $rowsFlattened = false;
        if ($data_sheet->countRows() > 1 && $this->getFlattenMultiRowInput() && $data_sheet->hasUidColumn(true)) {
            $uidCol = $data_sheet->getUidColumn();
            // Check if all column have the same values in every row (except the UID column)
            foreach ($data_sheet->getColumns() as $col) {
                if ($col === $uidCol) {
                    continue;
                }
                if (count(array_unique($col->getValues(false))) > 1) {
                    throw new ActionInputError($this, 'Mass update concistency error: different values on different input rows detected in column "' . $col->getName() . '" - expecting all rows to have same values in non-key columns!');
                }
            }
            
            // Flatten to a single row with a list of UIDs in the UID column
            $updateSheet = $data_sheet->copy();
            $updateSheet->getFilters()->addConditionFromColumnValues($uidCol);
            $rowsFlattened = true;
            $firstRow = $updateSheet->getRow(0);
            $firstRow[$uidCol->getName()] = implode($uidCol->getAttribute()->getValueListDelimiter(), array_unique($uidCol->getValues(false)));
            $updateSheet->removeRows()->addRow($firstRow);
            $task->setInputData($updateSheet);
        }
        
        if (! $this->isUpdateByFilter($updateSheet ?? $data_sheet)) {            
            // Don't use context filters!!! We've got everything we need at this point.
            // Adding context filters will lead to unexplainable behavior.
            $this->setUseContextFilters(false);
        }
        
        // Now the let the regular update perform the action
        $result = parent::perform($task, $transaction);

        // If we flattened rows befor, we need to copy all values from the flattened result
        // row back to each original input row - except for the UID, that we concatennated
        // previously!
        if ($rowsFlattened === true && $result instanceof ResultData) {
            $resultData = $result->getData();
            if ($resultData->countRows() === 1) {
                $uidColName = $uidCol->getName();
                $resultRow = $resultData->getRow(0);
                foreach ($data_sheet->getRows() as $rowIdx => $row) {
                    foreach ($resultRow as $fld => $val) {
                        if ($fld === $uidColName) {
                            continue;
                        }
                        $data_sheet->setCellValue($fld, $rowIdx, $val);
                    }
                }
            }
            $result->setData($data_sheet);
        }

        return $result;
    }
    
    /**
     * Returns TRUE if it's a mass update for all items matching the filters and FALSE if it's a UID-based update.
     * 
     * @param DataSheetInterface $inputData
     * @return bool
     */
    protected function isUpdateByFilter(DataSheetInterface $inputData) : bool
    {
        return $inputData->hasUidColumn(true) === false;
    }
    
    /**
     * Set to FALSE to perform the update on raw input data instead of flattening it to a single row.
     * 
     * @uxon-property flatten_multi_row_input
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return MassUpdateData
     */
    public function setFlattenMultiRowInput(bool $trueOrFalse) : MassUpdateData
    {
        $this->flattenMultiRowInput = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getFlattenMultiRowInput() : bool
    {
        return $this->flattenMultiRowInput;
    }
}