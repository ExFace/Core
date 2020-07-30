<?php
namespace exface\Core\Events\Widget;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Events\WidgetLinkEventInterface;

/**
 * Event fired when a widget link is created.
 * 
 * @event exface.Core.Widget.OnWidgetLinked
 * 
 * @author Andrej Kabachnik
 *        
 */
class OnWidgetLinkedEvent extends AbstractEvent implements WidgetLinkEventInterface
{
    private $link = null;
    
    /**
     * 
     * @param WidgetInterface $dataSheet
     */
    public function __construct(WidgetLinkInterface $widgetLink)
    {
        $this->link = $widgetLink;
    }

    /**
     * 
     * @return WidgetInterface
     */
    public function getWidgetLink() : WidgetLinkInterface
    {
        return $this->link;
    }
    
    public function getWorkbench()
    {
        return $this->link->getWorkbench();
    }
}