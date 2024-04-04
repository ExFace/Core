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
use exface\Core\CommonLogic\DataSheets\DataColumn;

class SaveData extends AbstractAction implements iModifyData, iCanBeUndone
{

    private $affected_rows = 0;

    private $undo_data_sheet = null;
    
    private $ignore_rows_if_empty_except_column_names = null;
    
    private $ignore_rows_if_empty_column_names = null;
    
    private $ignore_rows_if_empty = false;

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
        if ($data_sheet->isEmpty()) {
            $affected_rows = 0;
        } else {
            $affected_rows = $data_sheet->dataSave($transaction);
        }
        
        $message = $this->getResultMessageText() ?? $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SAVEDATA.RESULT', ['%number%' => $affected_rows], $affected_rows);
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
    
    protected function getIgnoreRowsIfEmpty() : bool
    {
        return $this->ignore_rows_if_empty;
    }
    
    /**
     * Set to TRUE to ignore input data rows that do not have any values.
     * 
     * **NOTE:** These rows will be removed and will not be present in the result data!
     * 
     * @uxon-property ignore_rows_if_empty
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return SaveData
     */
    protected function setIgnoreRowsIfEmpty(bool $value) : SaveData
    {
        $this->ignore_rows_if_empty = $value;
        return $this;
    }
    
    /**
     * 
     * @return array|NULL
     */
    protected function getIgnoreRowsIfEmptyExceptColumnNames() : ?array
    {
        return $this->ignore_rows_if_empty_except_column_names;
    }
    
    /** 
     * If a row is empty except in these columns, it will still be concidered empty and ignored
     * 
     * Setting the property with automatically set `ignore_rows_if_empty` to `true`!
     * 
     * This is particularly handy if you save matrix data like values per date where values
     * exist only fro certain "coordinates" (e.g. some dates do not have values) - if rows
     * without meaningfull values should not besaved, they can be ignored by adding the 
     * "coordinate" column to this list.
     * 
     * @uxon-property ignore_rows_if_empty_except_column_names
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $arrayOfColumnNames
     * @return SaveData
     */
    protected function setIgnoreRowsIfEmptyExceptColumnNames(UxonObject $arrayOfColumnNames) : SaveData
    {
        $this->ignore_rows_if_empty_except_column_names = $arrayOfColumnNames->toArray();
        $this->ignore_rows_if_empty = true;
        return $this;
    }
    
    /**
     * If a row has an empty value for one of these columns it will be concidered empty and ignored.
     * 
     * Setting the property with automatically set `ignore_rows_if_empty` to `true`!
     * 
     * This is particularly handy if you have an in table input field and wnat to sort out the rows
     * where the input field is empty.
     *
     * @uxon-property ignore_rows_if_empty_column_names
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfColumnNames
     * @return SaveData
     */
    protected function setIgnoreRowsIfEmptyColumnNames(UxonObject $arrayOfColumnNames)  : SaveData
    {
        $this->ignore_rows_if_empty_column_names = $arrayOfColumnNames->toArray();
        $this->ignore_rows_if_empty = true;
        return $this;
    }
    
    /**
     * If a row has an empty value for one of these attributes it will be concidered empty and ignored.
     * 
     * Setting the property with automatically set `ignore_rows_if_empty` to `true`!
     * 
     * This is particularly handy if you have an in table input field and wnat to sort out the rows
     * where the input field is empty.
     *
     * @uxon-property ignore_rows_if_empty_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfAliases
     * @return SaveData
     */
    protected function setIgnoreRowsIfEmptyAttributes(UxonObject $arrayOfAliases) : SaveData
    {
        foreach ($arrayOfAliases->toArray() as $alias) {
            $this->ignore_rows_if_empty_column_names[] = DataColumn::sanitizeColumnName($alias);
        }
        $this->ignore_rows_if_empty = true;
        return $this;
    }
    
    /**
     * 
     * @return array|NULL
     */
    protected function getIgnoreRowsIfEmptyColumnNames() : ?array
    {
        return $this->ignore_rows_if_empty_column_names;
    }
    
    /**
     * If a row is empty except for these attributes, it will still be concidered empty and ignored
     * 
     * Setting the property with automatically set `ignore_rows_if_empty` to `true`!
     * 
     * This is particularly handy if you save matrix data like values per date where values
     * exist only fro certain "coordinates" (e.g. some dates do not have values) - if rows
     * without meaningfull values should not besaved, they can be ignored by adding the 
     * "coordinate" column to this list.
     *
     * @uxon-property ignore_rows_if_empty_except_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfAliases
     * @return SaveData
     */
    protected function setIgnoreRowsIfEmptyExceptAttributes(UxonObject $arrayOfAliases) : SaveData
    {
        foreach ($arrayOfAliases->toArray() as $alias) {
            $this->ignore_rows_if_empty_except_column_names[] = DataColumn::sanitizeColumnName($alias);
        }
        $this->ignore_rows_if_empty = true;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isIgnoringRowsIfEmpty() : bool
    {
        return $this->ignore_rows_if_empty;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::getInputDataSheet()
     */
    public function getInputDataSheet(TaskInterface $task) : DataSheetInterface
    {
        $sheet = parent::getInputDataSheet($task);
        
        if ($this->isIgnoringRowsIfEmpty()) {
            $lb = $this->getLogBook($task);
            $lb->addSection('Removing empty rows');
            $lb->setIndentActive(1);
            $exceptCols = $this->getIgnoreRowsIfEmptyExceptColumnNames();
            if ($exceptCols !== null) {
                $lb->addLine('Removing rows if empty except for columns "' . implode('", "', $exceptCols) . '"');
                $rowIdxRemoved = [];
                for ($i = ($sheet->countRows()-1); $i >= 0; $i--) {
                    $row = $sheet->getRow($i);
                    foreach ($row as $colName => $val) {
                        if (in_array($colName, $exceptCols)) {
                            continue;
                        }
                        if ($sheet->getColumns()->get($colName)->getDataType()->isValueEmpty($val) === false) {
                            continue 2;
                        }
                    }
                    $rowIdxRemoved[] = $i;
                    $sheet->removeRow($i);
                }
                if (empty($rowIdxRemoved)) {
                    $lb->addLine('Removed rows ' . implode(', ', $rowIdxRemoved), 1);
                } else {
                    $lb->addLine('No rows removed', 1);
                }
            }
            $cols = $this->getIgnoreRowsIfEmptyColumnNames();
            if ($cols !== null) {
                $lb->addLine('Removing rows if at least one of these columns empty: "' . implode('", "', $cols) . '"');
                $rowIdxRemoved = [];
                for ($i = ($sheet->countRows()-1); $i >= 0; $i--) {
                    $row = $sheet->getRow($i);
                    foreach ($row as $colName => $val) {
                        if (! in_array($colName, $cols)) {
                            continue;
                        }
                        if ($sheet->getColumns()->get($colName)->getDataType()->isValueEmpty($val) === false) {
                            continue 2;
                        }
                    }
                    $rowIdxRemoved[] = $i;
                    $sheet->removeRow($i);
                }
                if (empty($rowIdxRemoved)) {
                    $lb->addLine('Removed rows ' . implode(', ', $rowIdxRemoved), 1);
                } else {
                    $lb->addLine('No rows removed', 1);
                }
            }
        }
        
        return $sheet;
    }
}