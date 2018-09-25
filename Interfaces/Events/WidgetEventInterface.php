<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\WidgetInterface;

interface WidgetEventInterface extends EventInterface
{
    /**
     * Returns the widget, for which the event was triggered.
     * 
     * @return WidgetInterface
     */
    public function getWidget() : WidgetInterface;
}