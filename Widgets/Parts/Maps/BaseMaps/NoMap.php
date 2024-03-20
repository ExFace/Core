<?php
namespace exface\Core\Widgets\Parts\Maps\BaseMaps;

use exface\Core\Widgets\Parts\Maps\AbstractBaseMap;
use exface\Core\Widgets\Map;

/**
 * Empty base map - e.g. to draw shapes on an empty canvas
 * 
 * @author Andrej Kabachnik
 *
 */
class NoMap extends AbstractBaseMap
{    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface::getCoordinateSystem()
     */
    public function getCoordinateSystem() : string
    {
        $userSetting = parent::getCoordinateSystem();
        return $userSetting === Map::COORDINATE_SYSTEM_AUTO ? Map::COORDINATE_SYSTEM_PIXELS : $userSetting;
    }
}