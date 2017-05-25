<?php

namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\CommonLogic\UxonObject;

interface DataAggregatorInterface extends iCanBeConvertedToUxon, iCanBeCopied
{

    function __construct(DataSheetInterface $data_sheet);

    public function getAttributeAlias();

    public function setAttributeAlias($value);

    public function getDataSheet();

    public function setDataSheet(DataSheetInterface $data_sheet);

    public function exportUxonObject();

    public function importUxonObject(UxonObject $uxon);

    /**
     * PRODUCT->SIZE:CONCAT(',') --> CONCAT(',')
     * 
     * @param string $attribute_alias            
     * @return string|boolean
     */
    public static function getAggregateFunctionFromAlias($attribute_alias);
}