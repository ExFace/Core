<?php
namespace exface\Core\Interfaces\Debug;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

interface DataLogBookInterface extends LogBookInterface
{
    public function addDataSheet(string $title, DataSheetInterface $dataSheet) : LogBookInterface;
}