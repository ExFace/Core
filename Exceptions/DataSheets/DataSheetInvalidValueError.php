<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\Exceptions\DataSheetValueExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if a value within the data sheet is not valid 
 * 
 * This exception can compute a user friendly message automatically, no $message, but $column and $rowIndexes
 * are provided in the constructor. This automatic message is very convenient as it will convert
 * row indexes (starting with 0) to row numbers (starting with 1), etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataSheetInvalidValueError extends DataSheetRuntimeError implements DataSheetValueExceptionInterface
{
    use DataSheetValueExceptionTrait;

    private $columnName = null;
    private $rowIndexes = null;
    private $messageWithoutRowNumbers = null;
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @param string|NULL $customMessage
     * @param string|NULL $alias
     * @param \Throwable|NULL $previous
     * @param DataColumnInterface|string|NULL $column
     * @param int[]|null $rowIndexes
     */
    public function __construct(DataSheetInterface $data_sheet, $customMessage = null, $alias = null, $previous = null, $column = null, array $rowIndexes = null)
    {
        if ($column !== null) {
            $this->columnName = ($column instanceof DataColumnInterface) ? $column->getName() : $column;
        }
        $this->rowIndexes = $rowIndexes;

        if ($customMessage !== null) {
            $this->messageWithoutRowNumbers = $customMessage;
        }
        
        if ($customMessage === null && $this->columnName !== null) {
            $col = $data_sheet->getColumns()->get($this->columnName);
            if ($col) {
                $this->messageWithoutRowNumbers = $this->generateMessageForColumn($col);
                $customMessage = $this->generateMessageForColumn($col, $this->getRowNumbers());
                $this->setUseExceptionMessageAsTitle(true);
                $this->setLogLevel($this->getDefaultLogLevel());
                $this->setAlias($this->getDefaultAlias());
            }
        }
        
        parent::__construct($data_sheet, $customMessage, $alias, $previous);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7K8KI39';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ERROR;
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
     * {@inheritDoc}
     * @see DataSheetValueExceptionInterface::getRowIndexes()
     */
    public function getRowIndexes() : ?array
    {
        return $this->rowIndexes;
    }

    /**
     * {@inheritDoc}
     * @see DataSheetValueExceptionInterface::getMessageTitleWithoutLocation()
     */
    public function getMessageTitleWithoutLocation() : string
    {
        return $this->messageWithoutRowNumbers;
    }
}