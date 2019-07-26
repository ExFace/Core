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
 * Tiles degrade to regular buttons if used in menus or toolbars unless the corresponding widget
 * supports tiles explicitly. In this case, display widgets will simply be ignored.
 *
 * ## Examples
 * 
 * ### Tile with a navigation-action and a counter
 * 
 * Example of a counter tile that will show the number of order in "pending" state
 * and will navigate to a detail page when clicked. The `KPI` widget will automatically
 * create a data sheet and count rows with pending state. Depending on the facade
 * used, the KPI data will even be loaded asynchronously.
 * 
 * ```
 * {
 *  "widget_type": "Tile",
 *  "object_alias": "my.App.ORDER",
 *  "title": "Orders",
 *  "subtitle": "To approve",
 *  "display_widget": {
 *      "wiget_type: "KPI",
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
 * ### Multiple tiles sharing data for content widgets
 * 
 * If you have multiple tiles, showing different figures for the same set of objects
 * (e.g. counters for different states of the orders above), you can make them load
 * their data with a single request by linking all KPIs together. 
 * 
 * In the following example, the KPI widget in the first `Tile` has a custom `data`
 * configuration, that includes a column for the next `Tile`. This `data` has an `id`,
 * which is used in the `data_widget_link` property of the `KPI` in the next `Tile`.
 * If the facade used supports bundling server requests, there will be only one read
 * request made for both tiles, which should result in significantly improved performance.
 * 
 * ```
 * {
 *  "widget_type": "Tile",
 *  "object_alias": "my.App.ORDER",
 *  "title": "Pending"
 *  "display_widget": {
 *      "wiget_type: "KPI",
 *      "attribute_alias": "STATE:COUNT_IF(= 'pending')"
 *      "data": {
 *          "id": "order_state_data",
 *          "columns": [
 *              {
 *                  "attribute_alias": "STATE:COUNT_IF(= 'pending')"
 *              },
 *              {
 *                  "attribute_alias": "STATE:COUNT_IF(= 'approved')"
 *              }
 *          ]
 *      }
 *  }
 * },
 * {
 *  "widget_type": "Tile",
 *  "object_alias": "my.App.ORDER",
 *  "title": "Pending"
 *  "display_widget": {
 *      "wiget_type: "KPI",
 *      "data_widget_link": "order_state_data",
 *      "attribute_alias": "STATE:COUNT_IF(= 'approved')"
 *  }
 * }
 * 
 * ```
 * 
 * ### Predefined filters for navigation actions
 * 
 * A common use for `Tiles` is linking into a more complex data widget with a predefined set of
 * filters. Here is how this can be done. Of course, such a tile can have content widgets too.
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
    public function getColor() : ?string
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