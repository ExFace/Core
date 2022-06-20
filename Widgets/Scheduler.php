<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\DataTimeline;
use exface\Core\Widgets\Parts\DataCalendarItem;
use exface\Core\Widgets\Parts\DataSchedulerResource;

/**
 * Shows a timeline with events per resource (lane) - like Outlook scheduling assistant.
 * 
 * @author Andrej Kabachnik
 *
 */
class Scheduler extends Data
{
    private $timelinePart = null;
    
    private $calendarItemPart = null;
    
    private $schedulerResourcePart = null;
    
    private $startDate = null;
    
    /**
     *
     * @return DataTimeline
     */
    public function getTimelineConfig() : DataTimeline
    {
        if ($this->timelinePart === null) {
            $this->timelinePart = new DataTimeline($this);
        }
        return $this->timelinePart;
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
        $this->timelinePart = new DataTimeline($this, $uxon);
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
        if ($this->calendarItemPart === null) {
            $this->calendarItemPart = new DataCalendarItem($this);
        }
        return $this->calendarItemPart;
    }
    
    /**
     * Defines, what data the calendar items should show.
     * 
     * @uxon-property items
     * @uxon-type \exface\Core\Widgets\Parts\DataCalendarItem
     * @uxon-template {"start_time": ""}
     * 
     * @param DataCalendarItem $uxon
     * @return Scheduler
     */
    public function setItems(UxonObject $uxon) : Scheduler
    {
        $this->calendarItemPart = new DataCalendarItem($this, $uxon);
        return $this;
    }
    
    /**
     *
     * @return DataSchedulerResource
     */
    public function getResourcesConfig() : DataSchedulerResource
    {
        return $this->schedulerResourcePart;
    }
    
    /**
     * Defines the resources (swimlanes) to be used in the scheduler.
     * 
     * @uxon-property resources
     * @uxon-type \exface\Core\Widgets\Parts\DataSchedulerResource
     * @uxon-template {"title": ""}
     * 
     * @param DataSchedulerResource $value
     * @return Scheduler
     */
    public function setResources(UxonObject $uxon) : Scheduler
    {
        $this->schedulerResourcePart = new DataSchedulerResource($this, $uxon);
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function hasResources() : bool
    {
        return $this->schedulerResourcePart !== null;
    }
    
    public function getStartDate() : ?string
    {
        return $this->startDate;
    }
    
    /**
     * The left-most date in the scheduler: can be a real date or a relative date - e.g. `-2w`.
     * 
     * If not set, the date of the first item will be used.
     * 
     * @uxon-property start_date
     * @uxon-type string
     * 
     * @param string $value
     * @return Scheduler
     */
    public function setStartDate(string $value) : Scheduler
    {
        $this->startDate = $value;
        return $this;
    }
}