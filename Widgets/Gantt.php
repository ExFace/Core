<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\DataTimeline;
use exface\Core\Widgets\Parts\DataCalendarItem;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

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
    
    private $childrenMoveWithParent = null;
    
    private $keepScrollPosition = false;
    
    private $autoRelayoutOnChange = false;

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
     * @return bool
     */
    public function getKeepScrollPosition() : bool
    {
        return $this->keepScrollPosition;
    }


    /**
     * If this is set to true, it will prevent the Gantt chart from scrolling back to the start position on the left after each rerender.
     * Set this to true if you are using multiple draggable taskbars on the same row.
     * 
     * @uxon-property keep_scroll_position
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return $this
     */
    public function setKeepScrollPosition(bool $value): Gantt
    {
        $this->keepScrollPosition = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAutoRelayoutOnChange() : bool
    {
        return $this->autoRelayoutOnChange;
    }

    /**
     * Automatically rearrange when dragging/resizing  the taskbars.
     * It is necessary if multiple bars are at the same row:
     * 
     * @uxon-property auto_relayout_on_change
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return $this
     */
    public function setAutoRelayoutOnChange(bool $value) : Gantt
    {
        $this->autoRelayoutOnChange = $value;
        return $this;
    }

    /**
     * Adds gantt view mode selection buttons to the toolbar
     * 
     * @param ButtonGroup $btnGrp
     * @param int $index
     * @param array $viewModes
     * @return void
     */
    public function addGanttViewModeButtons(ButtonGroup $btnGrp, int $index = 0, array $viewModes = []) : void
    {
        if (empty($viewModes)) {
            $viewModes = ['Day', 'Week', 'Month'];
        }

        $buttons = [];

        foreach ($viewModes as $viewMode) {
            $buttons[] = [
                'caption' => $viewMode,
                'action'  => [
                    'alias'  => 'exface.Core.CustomFacadeScript',
                    'hide_icon' => true,
                    'script' => <<<JS
                        sap.ui.getCore().byId('[#element_id:~input#]').gantt.change_view_mode('$viewMode');
JS
                ],
            ];
        }

        $btnGrp->addButton($btnGrp->createButton(new UxonObject([
            'widget_type' => 'MenuButton',
            'icon' => 'calendar',
            'hide_caption' => true,
            'buttons' => $buttons
        ])), $index);
    }
}