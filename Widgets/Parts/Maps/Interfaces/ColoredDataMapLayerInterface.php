<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Widgets\DataColumn;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface ColoredDataMapLayerInterface extends MapLayerInterface, iHaveColor, iHaveColorScale
{    
    public function getColorColumn() : ?DataColumn;
}