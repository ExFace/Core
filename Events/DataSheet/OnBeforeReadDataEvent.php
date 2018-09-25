<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Events\AbstractEvent;

/**
 * Event fired before a data sheet started reading it's data from the corresponding data sources.
 * 
 * @event exface.Core.DataSheet.OnBeforeReadData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeReadDataEvent extends AbstractEvent implements DataSheetEventInterface
{
    private $dataSheet = null;
    
    /**
     *
     * @param DataSheetInterface $dataSheet
     */
    public function __construct(DataSheetInterface $dataSheet)
    {
        $this->dataSheet = $dataSheet;
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
}