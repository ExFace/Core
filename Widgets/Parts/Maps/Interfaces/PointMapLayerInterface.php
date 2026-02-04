<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\Widgets\iHaveColor;

/**
 * Map layers, displaying points on a map
 * 
 * Points can have colors, sizes and values (labels).
 * 
 * @author Andrej Kabachnik
 *
 */
interface PointMapLayerInterface extends MapLayerInterface, iHaveColor
{
    const VALUE_POSITION_LEFT = 'left';

    const VALUE_POSITION_RIGHT = 'right';

    const VALUE_POSITION_TOP = 'top';

    const VALUE_POSITION_BOTTOM = 'bottom';

    const VALUE_POSITION_CENTER = 'center';
    
    /**
     * @return string
     */
    public function getValuePosition() : string;


    /**
     *
     * @return int
     */
    public function getPointSize() : int;
}