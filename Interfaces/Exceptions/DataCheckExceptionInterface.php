<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

Interface DataCheckExceptionInterface extends DataSheetExceptionInterface
{
    /**
     * 
     * @return DataCheckInterface|NULL
     */
    public function getCheck() : ?DataCheckInterface;
    
    /**
     * 
     * @return DataSheetInterface|NULL
     */
    public function getBadData() : ?DataSheetInterface;
}