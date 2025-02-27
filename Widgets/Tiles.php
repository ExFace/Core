<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;

/**
 * A special container for Tile widget - it makes it easier to create tile menus quickly.
 *
 * @author Andrej Kabachnik
 *        
 */
class Tiles extends WidgetGrid implements iFillEntireContainer
{    
    private $centerContent = null;
    
    private $hiddenIfEmpty = false;
    
    /**
     *
     * @return bool
     */
    public function getCenterContent(bool $default = false) : bool
    {
        return $this->centerContent ?? $default;
    }
    
    /**
     * Set to TRUE to place the content in the middle of the scree or FALSE for regular positioning.
     * 
     * @uxon-property center_content
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return Tiles
     */
    public function setCenterContent(bool $value) : Tiles
    {
        $this->centerContent = $value;
        return $this;
    }
    
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
     * @uxon-template [{"": ""}]
     * 
     * @param UxonObject $uxon
     * @return Tiles
     */
    public function setTiles(UxonObject $uxon) : Tiles
    {
        return $this->setWidgets($uxon);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\WidgetGrid::setWidgets()
     */
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
    
    /**
     *
     * @return bool
     */
    public function isHiddenIfEmpty() : bool
    {
        return $this->hiddenIfEmpty;
    }
    
    /**
     * Set to TRUE to hide the widget completely from users that won't see any tiles.
     *
     * @uxon-property hidden_if_empty
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return NavTiles
     */
    public function setHiddenIfEmpty(bool $value) : Tiles
    {
        $this->hiddenIfEmpty = $value;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        return null;
    }
}