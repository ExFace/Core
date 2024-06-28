<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Interfaces\Widgets\iCanBlink;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Interfaces\Widgets\iHaveColorWithOutline;
use exface\Core\Widgets\DataColumn;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface ColoredDataMapLayerInterface extends MapLayerInterface, iHaveColor, iHaveColorScale, iHaveColorWithOutline, iCanBlink
{    
    public function getColorColumn() : ?DataColumn;
}