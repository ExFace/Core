<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface WidgetLinkEventInterface extends EventInterface
{
    /**
     * Returns the widget, for which the event was triggered.
     * 
     * @return WidgetLinkInterface
     */
    public function getWidgetLink() : WidgetLinkInterface;
}