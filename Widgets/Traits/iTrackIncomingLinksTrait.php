<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Interfaces\Events\WidgetLinkEventInterface;

/**
 * Adds the method getValueLinksToThisWidget() to any widget
 * 
 * To use this trait add a listener to the `OnWidgetLinkedEvent` shortly after 
 * instantiating the widget - e.g. in the `init()` method:
 * 
 * ```
 *  protected function init()
 *  {
 *      parent::init();
 *      $this->getWorkbench()->eventManager()->addListener(OnWidgetLinkedEvent::getEventName(), [$this, 'handleWidgetLinkedEvent']);
 *  }
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
trait iTrackIncomingLinksTrait
{
    /**
     *
     * @var WidgetLinkInterface[]
     */
    private $incomingLinks = [];
    
    /**
     * Returns an array of widget links that point to this widget
     *
     * @return WidgetLinkInterface[]
     */
    public function getValueLinksToThisWidget() : array
    {
        return $this->incomingLinks;
    }
    
    /**
     *
     * @param WidgetLinkEventInterface $event
     * @return void
     */
    public function handleWidgetLinkedEvent(WidgetLinkEventInterface $event)
    {
        $link = $event->getWidgetLink();
        if ($link->getTargetWidgetId() !== $this->getId()) {
            return;
        }
        
        foreach ($this->incomingLinks as $existing) {
            if ($link->getSourceWidget() === $existing->getSourceWidget() && $link->getTargetColumnId() === $existing->getTargetColumnId()) {
                return;
            }
        }
        
        $this->incomingLinks[] = $event->getWidgetLink();
    }
}