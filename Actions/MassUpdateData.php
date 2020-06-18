<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Exceptions\Actions\ActionInputError;

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
        
        if ($data_sheet->countRows() > 1 && $this->getFlattenMultiRowInput() && $data_sheet->hasUidColumn(true)) {
            // Check if all column have the same values in every row (except the UID column)
            foreach ($data_sheet->getColumns() as $col) {
                if ($col === $data_sheet->getUidColumn()) {
                    continue;
                }
                if (count(array_unique($col->getValues(false))) > 1) {
                    throw new ActionInputError($this, 'Mass update concistency error: different values on different input rows detected in column "' . $col->getName() . '" - expecting all rows to have same values in non-key columns!');
                }
            }
            
            // Flatten to a single row with a list of UIDs in the UID column
            $data_sheet->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
            $firstRow = $data_sheet->getRow(0);
            $firstRow[$data_sheet->getUidColumn()->getName()] = implode($data_sheet->getUidColumn()->getAttribute()->getValueListDelimiter(), array_unique($data_sheet->getUidColumn()->getValues(false)));
            $data_sheet->removeRows()->addRow($firstRow);
            $task->setInputData($data_sheet);
            
            // Don't use context filters!!! We've got everything we need at this point.
            // Adding context filters will lead to unexplainable behavior.
            $this->setUseContextFilters(false);
        }
        
        // Now the 
        return parent::perform($task, $transaction);
    }
    
    /**
     * Set to FALSE to perform the update on raw input data instead of flattening it to a single row.
     * 
     * @uxon-property reduce_multi_row_input
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