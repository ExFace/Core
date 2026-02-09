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

    /**
     * Returns the position of the layer within its map widget (indexed starting with 0)
     * 
     * @return int
     */
    public function getIndex() : int;

    /**
     * @return bool
     */
    public function getShowPopupOnClick(): bool;

    /**
     * @param DataSheetInterface $sheet
     * @return DataSheetInterface
     */
    public function prepareDataSheetToRead(DataSheetInterface $sheet) : DataSheetInterface;

    /**
     * @param DataSheetInterface $sheet
     * @return DataSheetInterface
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $sheet) : DataSheetInterface;
}