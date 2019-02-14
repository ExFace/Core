<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\DataTimeline;
use exface\Core\Widgets\Parts\DataCalendarItem;

/**
 * Shows a timeline with events per resource (lane) - like Outlook scheduling assistant.
 * 
 * @author Andrej Kabachnik
 *
 */
class Scheduler extends Data
{
    private $timeline = null;
    
    private $calendarItem = null;
    
    /**
     *
     * @return DataTimeline
     */
    public function getTimelineConfig() : DataTimeline
    {
        if ($this->timeline === null) {
            $this->timeline = new DataTimeline($this);
        }
        return $this->timeline;
    }
    
    /**
     * Defines the options for the scheduler timeline.
     * 
     * @uxon-property timeline
     * @uxon-type \exface\Core\Widgets\Parts\DataTimeline
     * @uxon-template {"granularity": ""}
     * 
     * @param UxonObject $uxon
     * @return Scheduler
     */
    public function setTimeline(UxonObject $uxon) : Scheduler
    {
        $this->timeline = new DataTimeline($this, $uxon);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('timeline', $this->getTimelineConfig()->exportUxonObject());
        $uxon->setProperty('items', $this->getItemConfig()->exportUxonObject());
        return $uxon;
    }
    
    /**
     *
     * @return DataCalendarItem
     */
    public function getItemsConfig() : DataCalendarItem
    {
        if ($this->calendarItem === null) {
            $this->calendarItem = new DataCalendarItem($this);
        }
        return $this->calendarItem;
    }
    
    /**
     * Defines, what data the calendar items should show.
     * 
     * @uxon-property items
     * @uxon-type \exface\Core\Widgets\Parts\DataCalendarItem
     * @uxon-template {"start_time": "", "end_time": ""}
     * 
     * @param DataCalendarItem $uxon
     * @return Scheduler
     */
    public function setItems(UxonObject $uxon) : Scheduler
    {
        $this->calendarItem = new DataCalendarItem($this, $uxon);
        return $this;
    }
}