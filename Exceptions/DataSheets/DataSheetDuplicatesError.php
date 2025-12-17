<?php
namespace exface\Core\Exceptions\DataSheets;

use exface\Core\Exceptions\DuplicateError;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Exceptions\DataSheetExceptionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Widgets\DebugMessage;

/**
 * Exception thrown when an item marked already excisting/duplicate is tried to be created.
 * 
 * @author Ralf Mulansky
 *
 */
class DataSheetDuplicatesError extends DuplicateError implements DataSheetExceptionInterface
{
    use DataSheetExceptionTrait;
    
    private $logbook;
    
    /**
     *
     * @param DataSheetInterface $data_sheet
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(DataSheetInterface $data_sheet, $message, $alias = null, $previous = null, ?LogBookInterface $logbook = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setDataSheet($data_sheet);
        $this->logbook = $logbook;
    }
    
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = parent::createDebugWidget($debug_widget);
        if ($this->logbook !== null) {
            $this->logbook->createDebugWidget($debug_widget);
        }
        return $debug_widget;
    }
}