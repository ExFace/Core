<?php
namespace exface\Core\Interfaces\Debug;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface DataLogBookInterface extends LogBookInterface
{
    /**
     * 
     * @param string $title
     * @param DataSheetInterface $dataSheet
     * @return LogBookInterface
     */
    public function addDataSheet(string $title, DataSheetInterface $dataSheet) : LogBookInterface;
    
    /**
     * 
     * @return DataSheetInterface[]
     */
    public function getDataSheets() : array;
}