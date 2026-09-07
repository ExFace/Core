<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * Configures how aggregation popups display their member tasks in a data timeline.
 * 
 * Choose a list or experimental table layout, include overlapping upper-row tasks, and optionally display a compact Gantt chart beside the task list.
 * @experimental: All properties here are experemental! Dont use them in PROD yet!
 * 
 * @Author: Sergej Riel
 */
class DataTimelinePopupAggregation implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;

    const STYLE_LIST = 'list';
    const STYLE_TABLE = 'table';

    private DataTimeline $timeline;
    private string $style = self::STYLE_LIST;
    private bool $includeUpperRowTasks = false;
    private bool $expandTasks = false;
    private int $ganttWidth = 550;

    /**
     * Creates the aggregation popup configuration for a data timeline.
     *
     * @param DataTimeline $timeline
     * @param UxonObject|null $uxon
     */
    public function __construct(DataTimeline $timeline, ?UxonObject $uxon = null)
    {
        $this->timeline = $timeline;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }

    /**
     * {@inheritDoc}
     * @see WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->timeline->getWidget();
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->timeline->getWorkbench();
    }

    /**
     * Returns the aggregation popup member layout.
     *
     * @return string
     */
    public function getStyle() : string
    {
        return $this->style;
    }

    /**
     * Choose the aggregation popup member layout: `list` or `table`. The table layout is @experimental.
     *
     * @uxon-property style
     * @uxon-type [list,table]
     * @uxon-default list
     *
     * @param string $style
     * @return $this
     */
    public function setStyle(string $style) : DataTimelinePopupAggregation
    {
        $style = strtolower($style);
        if ($style !== self::STYLE_LIST && $style !== self::STYLE_TABLE) {
            throw new WidgetConfigurationError(
                $this->getWidget(),
                'Invalid timeline popup aggregate style "' . $style . '": please use list or table!'
            );
        }

        $this->style = $style;
        return $this;
    }

    /**
     * Returns whether overlapping visible upper-lane tasks are included.
     *
     * @return bool
     */
    public function getIncludeUpperRowTasks() : bool
    {
        return $this->includeUpperRowTasks;
    }

    /**
     * Includes overlapping visible upper-lane tasks in aggregation popups. Set to `false` to show only aggregate members. @experimental: Do not use it in PROD yet!
     * 
     * @uxon-property include_upper_row_tasks
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $includeUpperRowTasks
     * @return $this
     */
    public function setIncludeUpperRowTasks(bool $includeUpperRowTasks) : DataTimelinePopupAggregation
    {
        $this->includeUpperRowTasks = $includeUpperRowTasks;
        return $this;
    }

    /**
     * Returns whether a compact Gantt chart is shown beside the task list.
     *
     * @return bool
     */
    public function getExpandTasks() : bool
    {
        return $this->expandTasks;
    }

    /**
     * Shows a compact Gantt chart next to the aggregation popup task list. Use it in combination with the Uxon property "style: table" to achieve the best visual result! @experimental: Do not use it in PROD yet!
     *
     * @uxon-property expand_tasks
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $expandTasks
     * @return $this
     */
    public function setExpandTasks(bool $expandTasks) : DataTimelinePopupAggregation
    {
        $this->expandTasks = $expandTasks;
        return $this;
    }

    /**
     * Returns the compact popup Gantt width in pixels.
     *
     * @return int
     */
    public function getGanttWidth() : int
    {
        return $this->ganttWidth;
    }

    /**
     * Set the width in pixels for the compact popup Gantt. Used only when `popup_aggregation_expand_tasks` is `true`. @experimental: Do not use it in PROD yet!
     *
     * @uxon-property gantt_width
     * @uxon-type integer
     * @uxon-default 550
     *
     * @param int $ganttWidth
     * @return $this
     */
    public function setGanttWidth(int $ganttWidth) : DataTimelinePopupAggregation
    {
        $this->ganttWidth = $ganttWidth;
        return $this;
    }
}