<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface DataSheetEventInterface extends EventInterface
{
    /**
     * Returns the data sheet, for which the event was triggered.
     * 
     * @return DataSheetInterface
     */
    public function getDataSheet() : DataSheetInterface;
}