<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;

/**
 * Exception thrown if a value required for some operation on the data sheet is missing 
 * 
 * The most common use cases are required attributes when creating an object in the data source.
 * 
 * This exception can compute a user friendly message automatically, no $message, but $column and $rowIndexes
 * are provided in the constructor. This automatic message is very convenient as it will convert
 * row indexes (starting with 0) to row numbers (starting with 1), etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetMissingRequiredValueError extends DataSheetRuntimeError
{
    private $columnName = null;
    
    private $rowIndexes = null;
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @param string|NULL $message
     * @param string|NULL $alias
     * @param \Throwable|NULL $previous
     * @param DataColumnInterface|string|NULL $column
     * @param int[]|null $rowIndexes
     */
    public function __construct(DataSheetInterface $data_sheet, $message = null, $alias = null, $previous = null, $column = null, array $rowIndexes = null)
    {
        if ($column !== null) {
            $this->columnName = ($column instanceof DataColumnInterface) ? $column->getName() : $column;
        }
        $this->rowIndexes = $rowIndexes;
        
        if ($message === null && $this->columnName !== null) {
            $col = $data_sheet->getColumns()->get($this->columnName);
            if ($col) {
                $colCaption = $col->getAttribute()->getName();
                if ($this->rowIndexes !== null) {
                    $rowNoList = implode(', ', $this->getRowNumbers());
                    try {
                        $message = $data_sheet->getWorkbench()->getCoreApp()->getTranslator()->translate('DATASHEET.ERROR.MISSING_VALUES_ON_ROWS', ['%column%' => $colCaption, '%rows%' => $rowNoList]);
                    } catch (\Throwable $e) {
                        $data_sheet->getWorkbench()->getLogger()->logException($e);
                        $message = 'Missing values for "' . $colCaption . '" on row(s) ' . $rowNoList . '!';
                    }
                } else {
                    try {
                        $message = $data_sheet->getWorkbench()->getCoreApp()->getTranslator()->translate('DATASHEET.ERROR.MISSING_VALUES', ['%column%' => $colCaption, '%rows%' => $rowNoList]);
                    } catch (\Throwable $e) {
                        $data_sheet->getWorkbench()->getLogger()->logException($e);
                        $message = 'Missing values for "' . $colCaption . '"!';
                    }
                }
                $this->setUseExceptionMessageAsTitle(true);
            }
        }
        
        parent::__construct($data_sheet, $message, $alias, $previous);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '6T5UX3Q';
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getColumnName() : ?string
    {
        return $this->columnName;
    }
    
    /**
     * 
     * @return DataColumnInterface|NULL
     */
    public function getColumn() : ?DataColumnInterface
    {
        return $this->getDataSheet()->getColumns()->get($this->columnName);
    }
    
    /**
     * Returns the affected row indexes (starting with 0)
     * @return array|NULL
     */
    public function getRowIndexes() : ?array
    {
        return $this->rowIndexes;
    }
    
    /**
     * Returns the affected row numbers (starting with 1)
     * 
     * @return array|NULL
     */
    public function getRowNumbers() : ?array
    {
        return $this->rowIndexes === null ? null : array_map(function(int $rowIdx){ return $rowIdx + 1; }, $this->rowIndexes);
    }
}