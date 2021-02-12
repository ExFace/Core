<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Widgets\Map;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iHaveCaption;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface MapLayerInterface extends iHaveCaption
{    
    /**
     *
     * @return Map
     */
    public function getMap() : Map;
    
    /**
     * 
     * @return \Generator
     */
    public function getWidgets() : \Generator;
    
    public function prepareDataSheetToRead(DataSheetInterface $sheet) : DataSheetInterface;
    
    public function prepareDataSheetToPrefill(DataSheetInterface $sheet) : DataSheetInterface;
}