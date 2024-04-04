<?php
namespace exface\Core\Widgets\Parts\Maps\Interfaces;

use exface\Core\Widgets\DataColumn;

/**
 *
 * @author Andrej Kabachnik
 *
 */
interface ValueLabeledMapLayerInterface extends MapLayerInterface
{    
    public function getValueColumn() : ?DataColumn;
    
    public function hasValue() : bool;
}