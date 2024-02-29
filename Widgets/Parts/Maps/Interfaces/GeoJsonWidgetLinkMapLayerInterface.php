<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface GeoJsonWidgetLinkMapLayerInterface extends MapLayerInterface
{    
    /**
     * 
     * @return WidgetLinkInterface|NULL
     */
    public function getShapesWidgetLink() : ?WidgetLinkInterface;
}