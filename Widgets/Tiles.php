<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

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
     * @uxon-facade [{"": ""}]
     * 
     * @param UxonObject $uxon
     * @return Tiles
     */
    public function setTiles(UxonObject $uxon) : Tiles
    {
        return $this->setWidgets($uxon);
    }
    
    public function setWidgets($widget_or_uxon_array)
    {
        $widgets = array();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof UxonObject) {
                // If we have a UXON or instantiated widget object, use the widget directly
                $widgets[] = WidgetFactory::createFromUxon($this->getPage(), $w, $this, 'Tile');
            } else {
                // If it is something else, just add it to the result and let the parent object deal with it
                $widgets[] = $w;
            }
        }
        
        return parent::setWidgets($widgets);
    }
}