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
    
    /**
     * A list (array) of tiles to be displayed.
     * 
     * This is an (better understandable) alias for the `widgets` property - it has the
     * same effect.
     * 
     * @uxon-property tiles
     * @uxon-type \exface\Core\Widgets\Tile[]
     * 
     * @param UxonObject $uxon
     * @return Tiles
     */
    public function setTiles(UxonObject $uxon) : Tiles
    {
        return $this->setWidgets($uxon);
    }
}