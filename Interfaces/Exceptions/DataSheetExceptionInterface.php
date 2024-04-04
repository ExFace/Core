<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

Interface DataSheetExceptionInterface
{
    /**
     *
     * @return DataSheetInterface
     */
    public function getDataSheet() : DataSheetInterface;
}