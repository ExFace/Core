<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\CommonLogic\Log\Logger;

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
class DataSheetMissingRequiredValueError extends DataSheetInvalidValueError
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\DataSheets\DataSheetInvalidValueError::generateMessage()
     */
    protected function generateMessage(DataColumnInterface $col, array $rowNumbers = null) : ?string
    {
        $colCaption = $col->getAttribute()->getName();
        if ($rowNumbers !== null) {
            $rowNoList = implode(', ', $rowNumbers);
            try {
                $message = $col->getWorkbench()->getCoreApp()->getTranslator()->translate('DATASHEET.ERROR.MISSING_VALUES_ON_ROWS', ['%object%'=> $col->getMetaObject()->getName(), '%column%' => $colCaption, '%rows%' => $rowNoList]);
            } catch (\Throwable $e) {
                $col->getWorkbench()->getLogger()->logException($e);
                $message = 'Missing values for "' . $colCaption . '" on row(s) ' . $rowNoList . '!';
            }
        } else {
            try {
                $message = $col->getWorkbench()->getCoreApp()->getTranslator()->translate('DATASHEET.ERROR.MISSING_VALUES', ['%object%'=> $col->getMetaObject()->getName(), '%column%' => $colCaption]);
            } catch (\Throwable $e) {
                $col->getWorkbench()->getLogger()->logException($e);
                $message = 'Missing values for "' . $colCaption . '"!';
            }
        }
        return $message;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\DataSheets\DataSheetInvalidValueError::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '6T5UX3Q';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\DataSheets\DataSheetInvalidValueError::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return Logger::ERROR;
    }
}