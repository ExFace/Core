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
    private array $incomingLinks = [];
    private array $reportedLinksCount = [];
    
    /**
     * Returns an array of widget links that point to this widget
     * 
     * Since the links are collected from `OnWidgetLinked` events, they will get added here at different points in
     * time. So if you call this method once, there is no guarantee, that it will return the same result later too.
     * Use `getLinksToThisWidgetAddedSinceLastCall()` to get the links incrementally instead.
     *
     * @return WidgetLinkInterface[]
     */
    public function getLinksToThisWidget() : array
    {
        return $this->incomingLinks;
    }

    /**
     * Returns an array of widget links that have been added since the last time this method was called with the same $callId.
     * 
     * This allows to get the links incrementally. For example, we need all links every time `Data::getColumns()` is 
     * called to make sure all linked columns are marked as system columns. But we don't want to get all links every 
     * time, but only the new ones since the last call.
     * 
     * @param string $callId
     * @return WidgetLinkInterface[]
     */
    public function getLinksToThisWidgetAddedSinceLastCall(string $callId) : array
    {
        $links = $this->getLinksToThisWidget();
        $currentCnt = count($links);
        $lastCnt = $this->reportedLinksCount[$callId] ?? 0;
        $this->reportedLinksCount[$callId] = count($links);
        if ($currentCnt <= $lastCnt) {
            return [];
        } else {
            return array_slice($links, $lastCnt);
        }
    }
    
    /**
     *
     * @param WidgetLinkEventInterface $event
     * @return void
     */
    public function handleWidgetLinkedEvent(WidgetLinkEventInterface $event) : void
    {
        $link = $event->getWidgetLink();
        if ($link->getTargetWidgetId() !== $this->getId()) {
            return;
        }
        if ($link->hasSourceWidget() === true) {
            foreach ($this->incomingLinks as $existing) {
                if (
                    $link->getSourceWidget() === $existing->getSourceWidget()
                    && $link->getTargetColumnId() === $existing->getTargetColumnId()
                ) {
                    return;
                }
            }
        }
        
        $this->incomingLinks[] = $event->getWidgetLink();
    }
}