<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\DataTimeline;
use exface\Core\Widgets\Parts\DataCalendarItem;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * A Gantt widget shows a TreeTable together with a Gantt chart (horizontal timeline bars).
 * The Gantt Chart will show the tasks of the TreeTable as horizontal bars, 
 * where the length of the bar is determined by the start and end date of the task.
 * 
 * The `orientation` determines whether the table is shown to the left of the chart or above it.
 * It contains all the classic properties of a TreeTable, as well as the additional Gantt features.
 * 
 * The Gantt also supports nested data and can display multiple bars per rows.
 * You can read more about how to pass normal or nested data to the Gantt in the DataCalendarItem (task) documentation.
 * 
 * The Gantt timeline is highly adjustable. It allows you to create custom views like "days", "weeks", "months" and so on.
 * To learn more about the timeline please read the DataTimeline documentation.
 * 
 * ## Example:
 * 
 * ```
 * 
 *  {
 *      "widget_type": "Gantt",
 *      "object_alias": "...",
 *      "orientation": "horizontal",
 *      "freeze_columns": 2,
 *      "hide_header": false,
 *      "paginate": false,
 *      "aggregate_all": true,
 *      "hide_caption": false,
 *      "caption": "...",
 *      "filters": [{ ... }],
 *      "sorters": [{ ... }],
 *      "columns": [{ ... }],
 *      "timeline": { ... },
 *      "tasks": { ... },
 *      "buttons": [ { ... }]
 *  }
 * 
 * ```
 *
 * @author Andrej Kabachnik & Sergej Riel
 *
 */
class Gantt extends DataTree
{
    const ORIENTATION_HORIZONTAL = 'horizontal';
    const ORIENTATION_VERTICAL = 'vertical';

    private $timelinePart = null;
    
    private $taskPart = null;
    
    private $startDate = null;

    private $orientation = self::ORIENTATION_HORIZONTAL;

    private $hideSplitBar = false;
    
    private $childrenMoveWithParentIf = null;
    
    private $childrenMoveWithParent = null;

    /**
     * @inheritDoc
     * @see AbstractWidget::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        // We override to avoid timing issues, if these properties appear higher up in the UXON.
        $uxon->copy();
        
        $key = 'tasks';
        $tasksUxon = null;
        if($uxon->hasProperty($key)) {
            $tasksUxon = $uxon->getProperty($key);
            $uxon->unsetProperty($key);
        }

        $key = 'items';
        if($uxon->hasProperty($key)) {
            if($tasksUxon !== null) {
                throw new WidgetConfigurationError($this, 'Setting both "items" and "tasks" is not allowed!');
            }
            
            $tasksUxon = $uxon->getProperty($key);
            $uxon->unsetProperty($key);
        }

        parent::importUxonObject($uxon);
        $this->setTasks($tasksUxon);
    }

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
     *
     * @uxon-property items
     * @uxon-type \exface\Core\Widgets\Parts\DataCalendarItem
     * @uxon-template {"start_time": ""}
     * 
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
     * Returns the arrangement of the table and Gantt chart.
     *
     * @return string
     */
    public function getOrientation() : string
    {
        return $this->orientation;
    }

    /**
     * Arrange the table and Gantt chart horizontally or vertically.
     * 
     * With `horizontal`, the table is shown on the left and the Gantt chart on the right.
     * With `vertical`, the table is shown at the top and the Gantt chart at the bottom.
     *
     * @uxon-property orientation
     * @uxon-type [horizontal,vertical]
     * @uxon-default horizontal
     *
     * @param string $value
     * @return Gantt
     */
    public function setOrientation(string $value) : Gantt
    {
        $value = trim(strtolower($value));

        if ($value !== self::ORIENTATION_HORIZONTAL && $value !== self::ORIENTATION_VERTICAL) {
            throw new WidgetConfigurationError($this, 'Invalid Gantt orientation "' . $value . '": only "horizontal" or "vertical" are allowed!');
        }

        $this->orientation = $value;
        return $this;
    }

    /**
     * Returns whether the resize bar between the table and Gantt chart is hidden.
     *
     * @return bool
     */
    public function getHideSplitBar() : bool
    {
        return $this->hideSplitBar;
    }

    /**
     * If set to TRUE, the split Resize Bar is hidden
     *
     * @uxon-property hide_split_bar
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return Gantt
     */
    public function setHideSplitBar(bool $value) : Gantt
    {
        $this->hideSplitBar = $value;
        return $this;
    }
    
    /**
     * Move child bars with parent bar only if the child row matches these conditions
     * 
     * @uxon-property children_move_with_parent
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $trueOrFalse
     * @return Gantt
     */
    protected function setChildrenMoveWithParent(bool $trueOrFalse) : Gantt
    {
        if ($this->childrenMoveWithParentIf !== null && $trueOrFalse === false) {
            throw new WidgetConfigurationError($this, 'Cannot set `children_move_with_parent` to `false` while `children_move_with_parent_if` defined!');
        }
        $this->childrenMoveWithParent = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getChildrenMoveWithParent() : bool
    {
        if ($this->childrenMoveWithParentIf !== null) {
            return true;
        }
        return $this->childrenMoveWithParent ?? true;
    }
    
    /**
     * Move child bars with parent bar only if the child row matches these conditions
     * 
     * @uxon-property children_move_with_parent_if
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalProperty
     * @uxon-template {"operator": "AND", "conditions": [{"value_left": "", "comparator": "", "value_right": ""}]}
     *
     * @param UxonObject $uxon
     * @return DataCalendarItem
     */
    protected function setChildrenMoveWithParentIf(UxonObject $uxon) : Gantt
    {
        if ($this->childrenMoveWithParent === false) {
            throw new WidgetConfigurationError($this, 'Cannot set `children_move_with_parent_if` if `children_move_with_parent` is set to `false`');
        }
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



    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataTable::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach (parent::getChildren() as $child) {
            yield $child;
        }

        yield $this->getTasksConfig()->getPopup();
    }
}