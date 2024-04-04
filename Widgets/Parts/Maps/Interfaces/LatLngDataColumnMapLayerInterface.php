<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iUseData;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface LatLngDataColumnMapLayerInterface extends MapLayerInterface, iUseData
{    
    /**
     *
     * @return DataColumn
     */
    public function getLatitudeColumn() : DataColumn;
    
    /**
     *
     * @return DataColumn
     */
    public function getLongitudeColumn() : DataColumn;
    
    /**
     *
     * @return bool
     */
    public function hasTooltip() : bool;
    
    /**
     *
     * @return DataColumn|NULL
     */
    public function getTooltipColumn() : ?DataColumn;
}