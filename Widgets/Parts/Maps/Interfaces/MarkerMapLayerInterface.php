<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iHaveIcon;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface MarkerMapLayerInterface extends MapLayerInterface, iHaveColor, iHaveIcon
{    
    public function isClusteringMarkers() : ?bool;
}