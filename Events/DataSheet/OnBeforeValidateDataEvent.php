<?php
namespace exface\Core\Events\DataSheet;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;

/**
 * Event fired before a data sheet starts validating it's data in the corresponding data sources.
 * 
 * @event exface.Core.DataSheet.OnBeforeValidateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeValidateDataEvent extends AbstractEvent implements DataSheetEventInterface
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