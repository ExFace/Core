<?php

namespace exface\Core\Widgets\Parts\Tours;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 *
 * Represents a single waypoint step in a tour,
 * which can be associated with a specific widget and contains information
 * about the content and position of the popover that will be displayed to the user.
 * 
 * A TourStoryStep can be used for sorted tours, where the order of the steps matters. 
 * For unsorted tours please use the "tour_steps" property (TourWaypointStep) directly inside the widget definition.
 * 
 * - `widget_to_focus` property is a WidgetLink to a widget that will be highlighted when the step is active.
 * 
 *  ##Examples:
 *
 *  ```
 *   "steps": [
 *       {
 *           "widget_to_focus": "my-widget-id",
 * 
 *           "title": "New Column",
 *           "body": "This text will appear in the popover when the step is active.",
 *           "side": "bottom",
 *           "align": "center",
 *      },
 *  ]
 *  ```
 * 
 * @author Sergej Riel
 */
class TourStoryStep extends AbstractTourStep implements WidgetPartInterface
{
    private TourInterface $tour;
    
    /** @var WidgetLinkInterface */
    private $widgetToFocus = null; 
    public function __construct(TourInterface $tour, ?UxonObject $uxon = null)
    {
        $this->tour = $tour;
        if ($uxon) {
            $this->importUxonObject($uxon);
        }
    }
    
    public function getWidget(): WidgetInterface
    {
        return $this->tour->getWidget();
    }
    
    public function getWorkbench(): Workbench
    {
        return $this->tour->getWorkbench();
    }

    /**
     * @return WidgetLinkInterface|null
     */
    public function getWidgetToFocus() : ?WidgetLinkInterface
    {
        return $this->widgetToFocus;
    }

    /**
     * The focus widget is a WidgetLink to a widget that will be highlighted when the step is active.
     * 
     * @uxon-property widget_to_focus
     * @uxon-type \exface\Core\CommonLogic\WidgetLink
     * 
     * @param string|UxonObject|WidgetLinkInterface $widget_link_or_uxon_or_string
     * @return $this
     */
    protected function setWidgetToFocus(UxonObject|WidgetLinkInterface|string $widget_link_or_uxon_or_string) : TourStoryStep
    {
        if ($widget_link_or_uxon_or_string instanceof WidgetLinkInterface) {
            $this->widgetToFocus = $widget_link_or_uxon_or_string;
        } else {
            $this->widgetToFocus = WidgetLinkFactory::createFromWidget($this->tour->getWidget(), $widget_link_or_uxon_or_string);
        }
        return $this;
    }
}