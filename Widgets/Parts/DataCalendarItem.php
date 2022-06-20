<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\DataTypes\NumberDataType;

/**
 * Configuration for items in calendar-related data widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataCalendarItem implements WidgetPartInterface, iHaveColor, iHaveColorScale
{
    use DataWidgetPartTrait;
    
    use iHaveColorScaleTrait {
        getColorScale as getColorScaleViaTrait;
    }
    
    private $startTimeString = null;
    
    private $startTimeColumn = null;
    
    private $endTimeString = null;
    
    private $endTimeColumn = null;
    
    private $defaultDurationHours = null;
    
    private $titleString = null;
    
    private $titleColumn = null;
    
    private $subtitleString = null;
    
    private $subtitleColumn = null;
    
    private $colorExpr = null;
    
    private $colorColumn = null;
    
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
     * Start time for every item: attribute alias or formula
     * 
     * @uxon-property start_time
     * @uxon-type metamodel:attribute|metamodel:formula
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
     * End time for every item: attribute alias or formula
     * 
     * @uxon-property end_time
     * @uxon-type metamodel:attribute|metamodel:formula
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
     * @uxon-type metamodel:expression
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
     * @uxon-type metamodel:expression
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::getColor()
     */
    public function getColor(): ?string
    {
        if ($this->colorExpr === null || ! $this->colorExpr->isStatic()) {
            return null;
        }
        return $this->colorExpr->evaluate();
    }

    /**
     * The color of each appointment can be set to an attribute alias, a `=Formula()` or a CSS color value.
     * 
     * @uxon-property color
     * @uxon-type expression     * 
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->colorExpr = null;
        $this->colorColumn = null;
        $this->colorExpr = ExpressionFactory::createFromString($this->getWorkbench(), $color, $this->getMetaObject());
        if (! $this->colorExpr->isStatic()) {
            $this->colorColumn = $this->addDataColumn($color);
        }
        
        return $this;
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getColorColumn() : ?DataColumn
    {
        return $this->colorColumn;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::isColorScaleRangeBased()
     */
    public function isColorScaleRangeBased(): bool
    {
        return $this->colorExpr->getDataType() instanceof NumberDataType;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::getColorScale()
     */
    public function getColorScale() : array
    {
        $scale = $this->getColorScaleViaTrait();
        if (empty($scale) && null !== $colorCol = $this->getColorColumn()) {
            $colWidget = $colorCol->getCellWidget();
            if ($colWidget instanceof iHaveColorScale) {
                return $colWidget->getColorScale();
            }
        }
        return $scale;
    }
}