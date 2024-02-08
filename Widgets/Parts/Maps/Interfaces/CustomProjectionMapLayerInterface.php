<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface CustomProjectionMapLayerInterface extends MapLayerInterface
{
    public function getProjection() : MapProjectionInterface;
    
    /**
     *
     * @return bool
     */
    public function hasProjectionDefinition() : bool;
}