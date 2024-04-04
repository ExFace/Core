<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\Widgets\iHaveColor;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface MarkerMapLayerInterface extends MapLayerInterface, iHaveColor
{    
    public function isClusteringMarkers() : ?bool;
}