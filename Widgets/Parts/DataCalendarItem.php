<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;

/**
 * Configuration for items in calendar-related data widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataCalendarItem implements WidgetPartInterface
{
    use DataWidgetPartTrait;
    
    private $startTimeString = null;
    
    private $startTimeColumn = null;
    
    private $endTimeString = null;
    
    private $endTimeColumn = null;
    
    private $defaultDurationHours = null;
    
    private $titleString = null;
    
    private $titleColumn = null;
    
    private $subtitleString = null;
    
    private $subtitleColumn = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'start_time' => $this->getStartTimeColumn()->getAttributeAlias(),
            'title' => $this->getTitleColumn()->getAttributeAlias()
        ]);
        
        if ($this->hasEndTime()) {
            $uxon->setProperty('end_time', $this->getEndTimeColumn()->getAttributeAlias());
        }
        
        if ($this->hasSubtitle()) {
            $uxon->setProperty('subtitle', $this->getSubtitleColumn()->getAttributeAlias());
        }
        
        if ($this->defaultDurationHours !== null) {
            $uxon->setProperty('default_duration_hours', $this->defaultDurationHours);
        }
        
        return $uxon;
    }
    
    /**
     *
     * @return string
     */
    protected function getStartTime() : string
    {
        return $this->startTimeString;
    }
    
    /**
     * 
     * @param string $value
     * @return DataCalendarItem
     */
    public function setStartTime(string $value) : DataCalendarItem
    {
        $this->startTimeString = $value;
        $this->startTimeColumn = $this->addDataColumn($value);
        return $this;
    }
    
    public function getStartTimeColumn() : DataColumn
    {
        return $this->startTimeColumn;
    }
    
    /**
     *
     * @return string
     */
    protected function getEndTime() : ?string
    {
        return $this->endTimeString;
    }
    
    /**
     * 
     * @param string $value
     * @return DataCalendarItem
     */
    public function setEndTime(string $value) : DataCalendarItem
    {
        $this->endTimeString = $value;
        $this->endTimeColumn = $this->addDataColumn($value);
        return $this;
    }
    
    public function getEndTimeColumn() : DataColumn
    {
        return $this->endTimeColumn;
    }
    
    public function hasEndTime() : bool
    {
        return $this->endTimeString !== null;
    }
    
    /**
     *
     * @return int
     */
    public function getDefaultDurationHours(int $default = null) : ?int
    {
        return $this->defaultDurationHours ?? $default;
    }
    
    /**
     * The default duration will be used for the event length if no end-time is found in the data item.
     * 
     * Leave blank to let the facade decide itself (typically depending on the timeline granularity).
     * 
     * @uxon-property default_duration_hours
     * @uxon-type integer
     * 
     * @param int $value
     * @return DataCalendarItem
     */
    public function setDefaultDurationHours(int $value) : DataCalendarItem
    {
        $this->defaultDurationHours = $value;
        return $this;
    } 
    
    /**
     *
     * @return string
     */
    protected function getTitle() : string
    {
        return $this->titleString;
    }
    
    /**
     * Attribute alias or any other expression to be displayed as item title.
     * 
     * If not set explicitly, the object label will be used. If not present - the first
     * visible data column.
     * 
     * @uxon-property title
     * @uxon-type expression
     * 
     * @param string $value
     * @return DataCalendarItem
     */
    public function setTitle(string $expression) : DataCalendarItem
    {
        $this->titleString = $expression;
        $this->titleColumn = $this->addDataColumn($expression);
        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getTitleColumn() : DataColumn
    {
        if ($this->titleColumn === null) {
            if ($this->getMetaObject()->hasLabelAttribute()) {
                $this->titleColumn = $this->addDataColumn($this->getMetaObject()->getLabelAttribute()->getAlias());
            } else {
                foreach ($this->getDataWidget()->getColumns() as $col) {
                    if (false === $col->isHidden()) {
                        $this->titleColumn = $this->getDataWidget()->getColumns()[0];
                        break;
                    }
                }
            }
        }
        return $this->titleColumn;
    }
    
    
    
    /**
     *
     * @return string
     */
    protected function getSubtitle() : string
    {
        return $this->subtitleString;
    }
    
    /**
     * Attribute alias or any other expression to be displayed as item subtitle.
     *
     * If not set explicitly, the object label will be used. If not present - the first
     * visible data column.
     *
     * @uxon-property subtitle
     * @uxon-type expression
     *
     * @param string $value
     * @return DataCalendarItem
     */
    public function setSubtitle(string $expression) : DataCalendarItem
    {
        $this->subtitleString = $expression;
        $this->subtitleColumn = $this->addDataColumn($expression);
        return $this;
    }
    
    /**
     *
     * @return DataColumn
     */
    public function getSubtitleColumn() : ?DataColumn
    {
        return $this->subtitleColumn;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasSubtitle() : bool
    {
        return $this->subtitleString !== null;
    }
}