<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface LatLngWidgetLinkMapLayerInterface extends MapLayerInterface
{    
    public function getLatitudeWidgetLink() : ?WidgetLinkInterface;
    
    public function getLongitudeWidgetLink() : ?WidgetLinkInterface;
}