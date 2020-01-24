<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Events\AbstractEvent;

/**
 * Event fired before a data sheet started reading it's data from the corresponding data sources.
 * 
 * In addition to the data sheet to read (`$event->getDataSheet()`) the event also gives access
 * to the pagination arameters of the read operation: `$event->getLimit()` and `$event->getOffset()`.
 * 
 * Use `$event->preventRead()` to disable the default reading logic - i.e. performing a read-query
 * on the data source - and use custom logic to fill the data sheet from `$event->getDataSheet()`.
 * 
 * @event exface.Core.DataSheet.OnBeforeReadData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeReadDataEvent extends AbstractEvent implements DataSheetEventInterface
{
    private $dataSheet = null;
    
    private $preventRead = false;
    
    private $limit = null;
    
    private $offset = 0;
    
    /**
     *
     * @param DataSheetInterface $dataSheet
     */
    public function __construct(DataSheetInterface $dataSheet, int $limit = null, int $offset = 0)
    {
        $this->dataSheet = $dataSheet;
        $this->limit = $limit;
        $this->offset = $offset;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\DataSheetEventInterface::getDataSheet()
     */
    public function getDataSheet() : DataSheetInterface
    {
        return $this->dataSheet;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->dataSheet->getWorkbench();
    }
    
    /**
     * Prevents the default read operation.
     * 
     * Use this if the event handler fills the data sheet.
     * 
     * @return OnBeforeReadDataEvent
     */
    public function preventRead() : OnBeforeReadDataEvent
    {
        $this->preventRead = true;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isPreventRead() : bool
    {
        return $this->preventRead;
    }
    
    /**
     * Returns the maximum number of data rows to read (NULL by default).
     * 
     * @return int|NULL
     */
    public function getLimit() : ?int
    {
        return $this->limit;
    }
    
    /**
     * Returns the number of data rows to skip (0 by default).
     * 
     * @return int
     */
    public function getOffset() : int
    {
        return $this->offset;
    }
}