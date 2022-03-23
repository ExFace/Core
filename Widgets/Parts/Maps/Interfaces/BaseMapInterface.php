<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface BaseMapInterface extends MapLayerInterface
{
    /**
     * 
     * @return string
     */
    public function getCoordinateSystem() : string;
}