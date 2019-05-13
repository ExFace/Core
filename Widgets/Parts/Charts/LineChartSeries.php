<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Chart;
use exface\Core\Widgets\DataColumn;

class LineChartSeries extends AbstractChartSeries
{
    private $color = null;
    
    private $axis_x_attribute_alias = null;
    
    private $axis_x = null;
    
    private $axis_y_attribute_alias = null;
    
    private $axis_y = null;
    
    /**
     *
     * @var bool
     */
    private $stack = false;
    
    /**
     * 
     * @var bool
     */
    private $fill = false;
    
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
     * @return LineChartSeries
     */
    public function setColor(string $color) : LineChart
    {
        $this->color = $color;
        return $this;
    }
    
    public function getAxisX() : ChartAxis
    {
        if ($this->axis_x === null) {
            if ($this->axis_x_attribute_alias !== null) {
                $attr = $this->getMetaObject()->getAttribute($this->axis_x_attribute_alias);
                $axes = $this->getChart()->findAxesByAttribute($attr, Chart::AXIS_X);
                if (empty($axes)) {
                    $axis = $this->getChart()->createAxisFromExpression($this->axis_x_attribute_alias);
                    $this->getChart()->addAxisX($axis);
                } else {
                    $axis = $axes[0];
                }
            }
            if (! $axis) {
                throw new WidgetConfigurationError($this->getChart(), 'Cannot find x-axis for series ' . $this->getIndex() . ' of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
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
            if ($this->axis_y_attribute_alias !== null) {
                $attr = $this->getMetaObject()->getAttribute($this->axis_y_attribute_alias);
                $axes = $this->getChart()->findAxesByAttribute($attr, Chart::AXIS_Y);
                if (empty($axes)) {
                    $axis = $this->getChart()->createAxisFromExpression($this->axis_y_attribute_alias);
                    $this->getChart()->addAxisY($axis);
                } else {
                    $axis = $axes[0];
                }
            }
            if (! $axis) {
                throw new WidgetConfigurationError($this->getChart(), 'Cannot find y-axis for series ' . $this->getIndex() . ' of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
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
     * @see \exface\Core\Widgets\Parts\Charts\AbstractChartSeries::getCaption()
     */
    public function getCaption() : string
    {
        return $this->getAxisY()->getCaption();
    }
    
    public function getValueDataColumn(): DataColumn
    {
        return $this->getAxisY()->getDataColumn();
    }

    public function prepareData(iShowData $dataWidget): AbstractChartSeries
    {
        $this->getAxisX();
        $this->getAxisY();
        return $this;
    }
    
    public function isStacked() : bool
    {
        return $this->stack;
    }
    
    /**
     * Set to true to stack all series of this chart
     *
     * @uxon-property stack_series
     *
     * @param boolean $value
     * @return ColumnChartSeries
     */
    public function setStacked(bool $value) : ColumnChartSeries
    {
        $this->stack = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getFilled() : bool
    {
        return $this->fill;
    }
    
    /**
     * 
     * @param bool $value
     * @return LineChartSeries
     */
    public function setFilled(bool $value) : LineChartSeries
    {
        $this->fill = $value;
        return $this;
    }
}