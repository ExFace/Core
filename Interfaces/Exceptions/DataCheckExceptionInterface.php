<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSheets\DataCheckInterface;

Interface DataCheckExceptionInterface extends DataSheetExceptionInterface
{
    /**
     * 
     * @return DataCheckInterface|NULL
     */
    public function getCheck() : ?DataCheckInterface;
}