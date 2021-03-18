<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Widgets\Map;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Interfaces\Widgets\iHaveVisibility;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface MapLayerInterface extends iHaveCaption, iHaveVisibility
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