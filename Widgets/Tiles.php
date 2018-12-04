<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;

/**
 * A special container for Tile widget - it makes it easier to create tile menus quickly.
 *
 * @author Andrej Kabachnik
 *        
 */
class Tiles extends WidgetGrid
{    
    /**
     * 
     * @param callable $filter
     * @return Tile[]
     */
    public function getTiles(callable $filter = null) : array
    {
        return $this->getWidgets($filter);
    }
    
    public function setTiles(UxonObject $uxon) : Tiles
    {
        return $this->setWidgets($uxon);
    }
}