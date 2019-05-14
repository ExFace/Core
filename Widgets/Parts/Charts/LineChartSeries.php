<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Chart;
use exface\Core\Widgets\DataColumn;
use exface\Core\CommonLogic\UxonObject;

class LineChartSeries extends AbstractChartSeries
{
    private $color = null;
    
    private $axis_x_attribute_alias = null;
    
    private $axis_x_use_index = null;
    
    private $axis_x_column_id = null;
    
    private $axis_x = null;
    
    private $axis_y_attribute_alias = null;
    
    private $axis_y_use_index = null;
    
    private $axis_y_column_id = null;
    
    private $axis_y = null;
    
    private $valueColumnId = null;
    
    private $xColumn = null;
    
    private $yColumn = null;
    
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
            $cellWidget = $this->getValueDataColumn()->getCellWidget();
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
    public function setColor(string $color) : LineChartSeries
    {
        $this->color = $color;
        return $this;
    }
    
    public function getAxisX() : ChartAxis
    {
        if ($this->axis_x === null) {
            if ($this->getUseAxisX() !== null) {
                $axis = $this->getChart()->getAxesX()[$this->getUseAxisX()];
            } elseif ($this->axis_x_attribute_alias !== null) {
                $attr = $this->getMetaObject()->getAttribute($this->axis_x_attribute_alias);
                $axes = $this->getChart()->findAxesByAttribute($attr, Chart::AXIS_X);
                if (empty($axes)) {
                    $axis = $this->getChart()->createAxisFromExpression($this->axis_x_attribute_alias);
                    $this->getChart()->addAxisX($axis);
                } else {
                    $axis = $axes[0];
                }
            } elseif ($this->getAxisXColumnId() !== null) {
                $axis = $this->getChart()->getData()->getColumn($this->getAxisXColumnId());
            } else {
                $axis = $this->getChart()->getAxesX()[0];
            }
            if (! $axis) {
                throw new WidgetConfigurationError($this->getChart(), 'Cannot find x-axis for series ' . $this->getIndex() . ' of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
            }
            $this->axis_x = $axis;
        }
        return $this->axis_x;
    }
    
    protected function hasOwnXAxis() : bool
    {
        return $this->axis_x_use_index === null && ($this->axis_x_attribute_alias !== null || $this->axis_x_column_id !== null);
    }
    
    public function setAxisXAttributeAlias(string $alias) : LineChartSeries
    {
        $this->axis_x_attribute_alias = $alias;
        return $this;
    }
    
    public function getAxisY()
    {
        if ($this->axis_y === null) {
            if ($this->axis_y_use_index !== null) {
                $axis = $this->getChart()->getAxesY()[$this->axis_y_use_index];
            } elseif ($this->axis_y_attribute_alias !== null) {
                $attr = $this->getMetaObject()->getAttribute($this->axis_y_attribute_alias);
                $axes = $this->getChart()->findAxesByAttribute($attr, Chart::AXIS_Y);
                if (empty($axes)) {
                    $axis = $this->getChart()->createAxisFromExpression($this->axis_y_attribute_alias);
                    $this->getChart()->addAxisY($axis);
                } else {
                    $axis = $axes[0];
                }
            } elseif ($this->getAxisYColumnId() !== null) {
                $axis = $this->getChart()->getData()->getColumn($this->getAxisYColumnId());
            } else {
                $axis = $this->getChart()->getAxesY()[0];
            }
            if (! $axis) {
                throw new WidgetConfigurationError($this->getChart(), 'Cannot find y-axis for series ' . $this->getIndex() . ' of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
            }
            $this->axis_y = $axis;
        }
        return $this->axis_y;
    }
    
    protected function hasOwnYAxis() : bool
    {
        return $this->axis_y_use_index === null && ($this->axis_y_attribute_alias !== null || $this->axis_y_column_id !== null);
    }
    
    public function setAxisYAttributeAlias(string $alias) : LineChartSeries
    {
        $this->axis_y_attribute_alias = $alias;
        return $this;
    }
    
    public function getValueDataColumn(): DataColumn
    {
        if ($this->valueColumnId !== null) {
            $this->yColumn = $this->getChart()->getData()->getColumn($this->valueColumnId);
        } elseif ($this->hasOwnYAxis() === true) {
            $this->yColumn = $this->getAxisY()->getDataColumn();
        }
        
        return $this->yColumn;
    }
    
    public function setValueColumnId(string $widgetId) : LineChartSeries
    {
        $this->valueColumnId = $widgetId;
        return $this;
    }

    public function prepareData(iShowData $dataWidget): AbstractChartSeries
    {
        $this->getAxisX();
        if ($this->hasOwnXAxis() === false && $this->axis_x_attribute_alias !== null) {
            $xCol = $dataWidget->createColumnFromUxon(new UxonObject([
                'attribute_alias' => $this->axis_x_attribute_alias
            ]));
            $this->xColumn = $xCol;
            $dataWidget->addColumn($xCol);
            
        }
        $this->getAxisY();
        if ($this->hasOwnYAxis() === false && $this->axis_y_attribute_alias !== null) {
            $yCol = $dataWidget->createColumnFromUxon(new UxonObject([
                'attribute_alias' => $this->axis_y_attribute_alias
            ]));
            $this->yColumn = $yCol;
            $dataWidget->addColumn($yCol);
        }
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
    public function setStacked(bool $value) : LineChartSeries
    {
        $this->stack = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isFilled() : bool
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
    
    /**
     *
     * @return int
     */
    protected function getUseAxisX() : ?int
    {
        return $this->axis_x_use_index;
    }
    
    /**
     * 
     * @param int $value
     * @return LineChartSeries
     */
    public function setUseAxisX(int $value) : LineChartSeries
    {
        $this->axis_x_use_index = $value;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    protected function getUseAxisY() : ?int
    {
        return $this->axis_y_use_index;
    }
    
    /**
     *
     * @param int $value
     * @return LineChartSeries
     */
    public function setUseAxisY(int $value) : LineChartSeries
    {
        $this->axis_y_use_index = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getAxisXColumnId() : ?string
    {
        return $this->axis_x_column_id;
    }
    
    /**
     * 
     * @param string $value
     * @return LineChartSeries
     */
    public function setAxisXColumnId(string $value) : LineChartSeries
    {
        $this->axis_x_column_id = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getAxisYColumnId() : ?string
    {
        return $this->axis_y_column_id;
    }
    
    /**
     * 
     * @param string $value
     * @return LineChartSeries
     */
    public function setAxisYColumnId(string $value) : LineChartSeries
    {
        $this->axis_y_column_id = $value;
        return $this;
    }
    
}