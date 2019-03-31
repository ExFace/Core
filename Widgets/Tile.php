<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iDisplayValue;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iHaveColor;

/**
 * A Tile is basically a big fancy button, that can display additional information (KPIs, etc.).
 * 
 * The additional information widget can be defined in the display_widget property. 
 * As the name suggests, it can be a Display or one of it's derivatives: Text, 
 * ProgressBar, Microchart, etc.
 * 
 * Tiles are typically used to create navigation menus or deep-links in dashboards, 
 * but technically they can trigger any action. However, a link to the input widget 
 * must be defined for actions, that require input, as the tile will mostly be used 
 * stand-alone and not within a form or data widget.
 * 
 * Example of a counter tile that will show the number of order in "pending" state
 * and will navigate to a detail page when clicked.
 * 
 * ```
 * {
 *  "widget_type": "Tile",
 *  "object_alias": "my.App.ORDER",
 *  "title": "Orders",
 *  "subtitle": "To approve",
 *  "display_widget": {
 *      "attribute_alias": "STATE:COUNT_IF(= 'pending')"
 *  },
 *  "action": {
 *      "alias": "exface.Core.GoToPage",
 *      "page_alias": "my.App.orders-for-approval",
 *  }
 * }
 * 
 * ```
 * 
 * Example of a similar tile, but linked to a data widget. The DisplayTotal 
 * widget used for the tile is linked with the main widget of the target page 
 * of the GoTo-action and displays the COUNT of it's ORDER_NO column. This ensures, 
 * that the information in the tile allways corresponds to the initial state of the
 * widget the tile will show when clicked - regardless of the exact filters,
 * aggregations or other configuration of that widget.
 *  
 * ```
 * {
 *  "widget_type": "Tile",
 *  "object_alias": "my.App.ORDER",
 *  "title": "Orders",
 *  "subtitle": "To approve",
 *  "display_widget": {
 *      "widget_type": "DisplayTotal",
 *      "data_link": "my.App.orders-for-approval",
 *      "attribute_alias": "ORDER_NO",
 *      "aggregator": "COUNT"
 *  },
 *  "action": {
 *      "alias": "exface.Core.GoToPage",
 *      "page_alias": "my.App.orders-for-approval",
 *  }
 * }
 * 
 * ```
 * 
 * Example of a navigation tile with preset filters and not display widget:
 * 
 * ```
 * {
 *  "widget_type": "Tile",
 *  "object_alias": "my.App.DELIVERY",
 *  "title": "Deliveries",
 *  "subtitle": "Expected today",
 *  "action": {
 *      "alias": "exface.Core.GoToPage",
 *      "page_alias": "my.App.Deliveries",
 *      "input_data_sheet": {
 *          "object_alias": "my.App.DELIVERY",
 *          "filters": {
 *              "operator": "AND",
 *              "conditions": [
 *                  {
 *                      "object_alias": "my.App.DELIVERY",
 *                      "expression": "ETA",
 *                      "comparator": ">",
 *                      "value": "-1"
 *                  },
 *                  {
 *                      "object_alias": "my.App.DELIVERY",
 *                      "expression": "ETA",
 *                      "comparator": "<=",
 *                      "value": "0"
 *                  }
 *              ]
 *          }
 *      }
 *  }
 * }
 * 
 * ```
 *  
 * Tiles degrade to regular buttons if used in menus or toolbars unless the corresponding widget
 * supports tiles explicitly. In this case, display widgets will simply be ignored.
 *
 * @author Andrej Kabachnik
 *        
 */
class Tile extends Button implements iHaveColor
{
    private $subtitle = null;
    
    private $displayWidget = null;
    
    private $color = null;
    
    /**
     * Returns the title of the tile or NULL if no title was set.
     * 
     * @return string|null
     */
    public function getTitle()
    {
        return $this->getCaption();
    }
    
    /**
     * Sets a title for the tile: if not set, the title will be autogenerated from the action.
     * 
     * Technically this is the same as setting the caption.
     * 
     * @uxon-property title
     * @uxon-type string
     * 
     * @param string $text
     * @return \exface\Core\Widgets\AbstractWidget
     */
    public function setTitle($text)
    {
        return $this->setCaption($text);
    }
    
    /**
     * Returns the subtitle of the tile or NULL if no subtitle was set.
     * 
     * @return string|null
     */
    public function getSubtitle()
    {
        return $this->subtitle;
    }

    /**
     * Sets the subtite of the tile - if not set, the tile will ony have a title.
     * 
     * @uxon-property subtitle
     * @uxon-type string|metamodel:formula
     * 
     * @param mixed $subtitle
     * @return Tile
     */
    public function setSubtitle($text)
    {
        $this->subtitle = $this->evaluatePropertyExpression($text);
        return $this;
    }

    /**
     * Returns the additional widget to display in the tile or throws 
     * an exception if no display widget was defined.
     * 
     * @see hasDisplayWidget() to check if there is a display widget defined.
     * 
     * @throws WidgetChildNotFoundError
     * @return iDisplayValue
     */
    public function getDisplayWidget()
    {
        if (is_null($this->displayWidget)) {
            throw new WidgetChildNotFoundError($this, 'The display widget of a ' . $this->getWidgetType() . ' was requested, while no such widget was defined!');
        }
        return $this->displayWidget;
    }

    /**
     * Defines the additional information widget to display in the tile.
     * 
     * If no widget type is specified explicitly the generic Display widget
     * will be used.
     * 
     * @uxon-property display_widget
     * @uxon-type \exface\Core\Widgets\Display
     * 
     * @param UxonObject|iDisplayValue $displayWidget
     * @throws WidgetConfigurationError
     * @return Tile
     */
    public function setDisplayWidget($uxon_or_widget)
    {
        if ($uxon_or_widget instanceof iDisplayValue) {
            $widget = $uxon_or_widget;
        } elseif ($uxon_or_widget instanceof UxonObject) {
            $widget = WidgetFactory::createFromUxon($this->getPage(), $uxon_or_widget, $this, 'Display');
        } else {
            throw new WidgetConfigurationError($this, 'Invalid definition of the dipslay widget in a ' . $this->getWidgetType() . ': expecting a UXON description or an instantiated display widget, received "' . gettype($uxon_or_widget) . '" instead!');
        }
        $this->displayWidget = $widget;
        return $this;
    }
    
    /**
     * Returns TRUE if the tile has a display widget and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasDisplayWidget()
    {
        try {
            $this->getDisplayWidget();
        } catch (WidgetChildNotFoundError $e) {
            return false;
        }
        return true;
    }
    
    /**
     * 
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Changes the color of the tile to any HTML color value (or other facade-specific value)
     * 
     * @uxon-property color
     * @uxon-type color|string
     * 
     * @param string $color
     * @return \exface\Core\Widgets\Tile
     */
    public function setColor($color)
    {
        $this->color = $color;
        return $this;
    }
    
}
?>