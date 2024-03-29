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
        hasColorScale as hasColorScaleViaTrait;
    }
    
    private $startTimeExprString = null;
    
    private $startTimeColumn = null;
    
    private $endTimeExprString = null;
    
    private $endTimeColumn = null;
    
    private $defaultDurationHours = null;
    
    private $titleString = null;
    
    private $titleColumn = null;
    
    private $subtitleString = null;
    
    private $subtitleColumn = null;
    
    private $colorExpr = null;
    
    private $colorColumn = null;
    
    private $indicator = null;
    
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
        
        if ($this->indicator !== null) {
            $uxon->setProperty('indicator', $this->indicator->exportUxonObject());
        }
        
        return $uxon;
    }
    
    /**
     *
     * @return string
     */
    protected function getStartTime() : string
    {
        return $this->startTimeExprString;
    }
    
    /**
     * Start time for every item: attribute alias or formula
     * 
     * @uxon-property start_time
     * @uxon-type metamodel:attribute|metamodel:formula
     * @uxon-required true
     * 
     * @param string $value
     * @return DataCalendarItem
     */
    public function setStartTime(string $value) : DataCalendarItem
    {
        $this->startTimeExprString = $value;
        $this->startTimeColumn = null;
        $this->addDataColumn($value);
        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getStartTimeColumn() : DataColumn
    {
        if ($this->startTimeColumn === null) {
            $this->startTimeColumn = $this->getDataWidget()->getColumnByExpression($this->startTimeExprString);
        }
        return $this->startTimeColumn;
    }
    
    /**
     *
     * @return string
     */
    protected function getEndTime() : ?string
    {
        return $this->endTimeExprString;
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
        $this->endTimeExprString = $value;
        $this->endTimeColumn = null;
        $this->addDataColumn($value);
        return $this;
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getEndTimeColumn() : ?DataColumn
    {
        if ($this->endTimeColumn === null && $this->endTimeExprString !== null) {
            $this->endTimeColumn = $this->getDataWidget()->getColumnByExpression($this->endTimeExprString);
        }
        return $this->endTimeColumn;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasEndTime() : bool
    {
        return $this->endTimeExprString !== null;
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
        $this->titleColumn = null;
        $this->addDataColumn($expression);
        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getTitleColumn() : DataColumn
    {
        if ($this->titleColumn === null) {
            if ($this->titleString !== null) {
                $this->titleColumn = $this->getDataWidget()->getColumnByExpression($this->titleString);
            } elseif ($this->getMetaObject()->hasLabelAttribute()) {
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
        $this->subtitleColumn = null;
        $this->addDataColumn($expression);
        return $this;
    }
    
    /**
     *
     * @return DataColumn
     */
    public function getSubtitleColumn() : ?DataColumn
    {
        if ($this->subtitleColumn === null) {
            $this->subtitleColumn = $this->getDataWidget()->getColumnByExpression($this->subtitleString);
        }
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
     * @uxon-type metamodel:expression|color 
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveColor::setColor()
     */
    public function setColor($color)
    {
        $this->colorColumn = null;
        $this->colorExpr = ExpressionFactory::createFromString($this->getWorkbench(), $color, $this->getMetaObject());
        if ($this->hasColorColumn()) {
            $this->addDataColumn($color);
        }
        
        return $this;
    }
    
    public function hasColorColumn() : bool
    {
        return $this->colorExpr !== null && ! $this->colorExpr->isStatic();
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getColorColumn() : ?DataColumn
    {
        if ($this->colorColumn === null && $this->hasColorColumn()) {
            $this->colorColumn = $this->getDataWidget()->getColumnByExpression($this->colorExpr);
        }
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
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveColorScale::hasColorScale()
     */
    public function hasColorScale() : bool
    {
        $value = $this->hasColorScaleViaTrait();
        if ($value === false && null !== $colorCol = $this->getColorColumn()){
            $colWidget = $colorCol->getCellWidget();
            if ($colWidget instanceof iHaveColorScale) {
                return $colWidget->hasColorScale();
            }
        }
        return $value;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasIndicator() : bool
    {
        return $this->indicator !== null;
    }
    
    /**
     * 
     * @return DataItemIndicator|NULL
     */
    public function getIndicatorConfig() : ?DataItemIndicator
    {
        return $this->indicator;
    }
    
    /**
     * Each calendar item can have an indicator with a different color - e.g. representing a status or similar.
     * 
     * The indicator is independant of the main color of the event. Depending on the facade
     * used, it may be rendered as a stripe on the side of the event bar or an icon inside
     * of it.
     *
     * @uxon-property indicator
     * @uxon-type \exface\Core\Widgets\Parts\DataItemIndicator
     * @uxon-template {"color": ""}
     *
     * @param DataCalendarItem $uxon
     * @return DataCalendarItem
     */
    public function setIndicator(UxonObject $uxon) : DataCalendarItem
    {
        $this->indicator = new DataItemIndicator($this->getDataWidget(), $uxon);
        return $this;
    }
}