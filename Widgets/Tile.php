<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iDisplayValue;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

/**
 * A Tile is basically a big fancy button, that can display additional information (KPIs, etc.).
 * 
 * Tiles are typically used to create navigation menus or deep-links in dashboards.
 *
 * @author Andrej Kabachnik
 *        
 */
class Tile extends Button
{
    private $subtitle = null;
    
    private $displayWidget = null;
    
    public function getTitle()
    {
        return $this->getCaption();
    }
    
    public function setTitle($text)
    {
        return $this->setCaption($text);
    }
    
    /**
     * @return mixed
     */
    public function getSubtitle()
    {
        return $this->subtitle;
    }

    /**
     * 
     * @param mixed $subtitle
     * @return Tile
     */
    public function setSubtitle($text)
    {
        $this->subtitle = $text;
        return $this;
    }

    /**
     * 
     * @return iDisplayValue
     */
    public function getDisplayWidget()
    {
        return $this->displayWidget;
    }

    /**
     * 
     * @param UxonObject|iDisplayValue $displayWidget
     * @return Tile
     */
    public function setDisplayWidget($uxon_or_widget)
    {
        if ($uxon_or_widget instanceof iDisplayValue) {
            $widget = $uxon_or_widget;
        } elseif ($uxon_or_widget instanceof UxonObject) {
            $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon_or_widget, $this, 'Display');
        }
        $this->displayWidget = $widget;
        return $this;
    }

    
    
}
?>