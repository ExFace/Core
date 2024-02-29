<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\DataTimeline;
use exface\Core\Widgets\Parts\DataCalendarItem;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Widgets\Parts\ConditionalPropertyCondition;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class Gantt extends DataTree
{
    private $timelinePart = null;
    
    private $taskPart = null;
    
    private $schedulerResourcePart = null;
    
    private $startDate = null;
    
    private $childrenMoveWithParentIf = null;
    
    private $childrenMoveWithParent = true;
    
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
     * Defines the options for the time scale.
     *
     * @uxon-property timeline
     * @uxon-type \exface\Core\Widgets\Parts\DataTimeline
     * @uxon-template {"granularity": ""}
     *
     * @param UxonObject $uxon
     * @return Gantt
     */
    public function setTimeline(UxonObject $uxon) : Gantt
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
        $uxon->setProperty('tasks', $this->getTaskConfig()->exportUxonObject());
        return $uxon;
    }
    
    /**
     *
     * @return DataCalendarItem
     */
    public function getTasksConfig() : DataCalendarItem
    {
        if ($this->taskPart === null) {
            $this->taskPart = new DataCalendarItem($this);
        }
        return $this->taskPart;
    }
    
    /**
     * Defines, what data the calendar tasks should show.
     *
     * @uxon-property tasks
     * @uxon-type \exface\Core\Widgets\Parts\DataCalendarItem
     * @uxon-template {"start_time": ""}
     *
     * @param DataCalendarItem $uxon
     * @return Gantt
     */
    public function setTasks(UxonObject $uxon) : Gantt
    {
        $this->taskPart = new DataCalendarItem($this, $uxon);
        return $this;
    }
    
    /**
     * Same as setTasks() - just for better compatibility with Scheduler widget.
     * @param UxonObject $uxon
     * @return Gantt
     */
    protected function setItems(UxonObject $uxon) : Gantt
    {
        return $this->setTasks($uxon);
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getStartDate() : ?string
    {
        return $this->startDate;
    }
    
    /**
     * @uxon-property children_move_with_parent
     * @uxon-type boolean
     *
     * @param bool $trueOrFalse
     * @return Gantt
     */
    protected function setChildrenMoveWithParent(bool $trueOrFalse) : Gantt
    {
        $this->childrenMoveWithParent = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getChildrenMoveWithParent() : bool
    {
        if ($this->getChildrenMoveWithParentIf() !== null) {
            return true;
        } else return $this->childrenMoveWithParent;
    }
    
    /**
     * @uxon-property children_move_with_parent_if
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalProperty
     * @uxon-template {"operator": "AND", "conditions": [{"value_left": "", "comparator": "", "value_right": ""}]}
     *
     * @param UxonObject $uxon
     * @return DataCalendarItem
     */
    protected function setChildrenMoveWithParentIf(UxonObject $uxon) : Gantt
    {
        $this->childrenMoveWithParentIf = $uxon;
        return $this;
    }
    
    /**
     *
     * @return ConditionalProperty|NULL
     */
    public function getChildrenMoveWithParentIf() : ?ConditionalProperty
    {
        if ($this->childrenMoveWithParentIf === null) {
            return null;
        }
        
        if (! ($this->childrenMoveWithParentIf instanceof ConditionalProperty)) {
            $this->childrenMoveWithParentIf = new ConditionalProperty($this, 'childrenMoveWithParentIf', $this->childrenMoveWithParentIf);
        }
        
        return $this->childrenMoveWithParentIf;
    }

    /**
     * The left-most date in the scheduler: can be a real date or a relative date - e.g. `-2w`.
     *
     * If not set, the date of the first task will be used.
     *
     * @uxon-property start_date
     * @uxon-type string
     *
     * @param string $value
     * @return Gantt
     */
    public function setStartDate(string $value) : Gantt
    {
        $this->startDate = $value;
        return $this;
    }
}