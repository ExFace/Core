<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface GeoJsonMapLayerInterface extends MapLayerInterface
{
    public function getOpacity() : ?float;
    
    public function getLineWeight() : ?float;
}