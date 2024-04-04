<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\DuplicateError;
use exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Exception thrown when an item marked already excisting/duplicate is tried to be created.
 * 
 * @author Ralf Mulansky
 *
 */
class DataSheetDuplicatesError extends DuplicateError implements DataSheetExceptionInterface
{
    use DataSheetExceptionTrait;
    
    /**
     *
     * @param DataSheetInterface $data_sheet
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(DataSheetInterface $data_sheet, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataSheet($data_sheet);
    }
}