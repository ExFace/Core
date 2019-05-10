<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Chart;

class LineChart extends AbstractChartType
{
    private $color = null;
    
    private $axis_x_attribute_alias = null;
    
    private $axis_x = null;
    
    private $axis_y_attribute_alias = null;
    
    private $axis_y = null;
    
    /**
     * Returns the color of this series or NULL if no color explicitly defined.
     *
     * @return string|NULL
     */
    public function getColor() : ?string
    {
        if (is_null($this->color)) {
            $cellWidget = $this->getDataColumn()->getCellWidget();
            if ($cellWidget instanceof iHaveColor) {
                $this->color = $cellWidget->getColor();
            }
        }
        return $this->color;
    }
    
    /**
     * Sets a specific color for the series - if not set, facades will use their own color scheme.
     *
     * HTML color names are supported by default. Additionally any color selector supported by
     * the current facade can be used. Most HTML facades will support css colors.
     *
     * @link https://www.w3schools.com/colors/colors_groups.asp
     *
     * @uxon-property color
     * @uxon-type string
     *
     * @param string $color
     * @return LineChart
     */
    public function setColor(string $color) : LineChart
    {
        $this->color = $color;
        return $this;
    }
    
    public function getAxisX() : ChartAxis
    {
        if ($this->axis_x === null) {
            $axis = $this->getChart()->findAxis($this->getDataColumn(), Chart::AXIS_X);
            if (! $axis) {
                $axis = $this->getChart()->getAxesY()[0];
            }
            if (! $axis) {
                throw new WidgetConfigurationError($this->getLineChart(), 'Cannot find x-axis for series "' . $this->getId() . '" of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
            }
            $this->axis_x = $axis;
        }
        return $this->axis_x;
    }
    
    public function setAxisXAttributeAlias(string $alias) : LineChart
    {
        $this->axis_x_attribute_alias = $alias;
        return $this;
    }
    
    public function getAxisY()
    {
        if ($this->axis_y === null) {
            $axis = $this->getChart()->findAxis($this->getDataColumn(), Chart::AXIS_Y);
            if (! $axis) {
                $axis = $this->getChart()->getAxesY()[0];
            }
            if (! $axis) {
                throw new WidgetConfigurationError($this->getLineChart(), 'Cannot find y-axis for series "' . $this->getChartType() . '" of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
            }
            $this->axis_y = $axis;
        }
        return $this->axis_y;
    }
    
    public function setAxisYAttributeAlias(string $alias) : LineChart
    {
        $this->axis_y_attribute_alias = $alias;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\AbstractChartType::getCaption()
     */
    public function getCaption() : string
    {
        return $this->getAxisY()->getCaption();
    }
}