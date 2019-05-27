<?php
namespace exface\Core\Widgets\Parts\Charts\Traits;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Chart;
use exface\Core\Widgets\DataColumn;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Charts\ChartAxis;
use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Widgets\Parts\Charts\Interfaces\StackableChartSeriesInterface;

trait XYChartSeriesTrait
{
    private $color = null;
    
    /**
     * @var string
     */
    private $xAttributeAlias = null;
    
    /**
     * @var int
     */
    private $xAxisNo = null;
    
    /**
     * @var string
     */
    private $xColumnId = null;
    
    /**
     * @var DataColumn
     */
    private $xColumn = null;
    
    /**
     * @var ChartAxis
     */
    private $xAxis = null;
    
    /**
     * @var string
     */
    private $yAttributeAlias = null;
    
    /**
     * @var int
     */
    private $yAxisNo = null;
    
    /**
     * @var string
     */
    private $yColumnId = null;
    
    /**
     * @var ChartAxis
     */
    private $yAxis = null;
    
    /**
     * @var DataColumn
     */
    private $yColumn = null;
    
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
     * @return ChartSeries
     */
    public function setColor(string $color) : ChartSeries
    {
        $this->color = $color;
        return $this;
    }
    
    /**
     * 
     * @return ChartAxis
     */
    public function getXAxis() : ChartAxis
    {
        return $this->xAxis;
    }
    
    /**
     * Use this attribute for X-values
     * 
     * @uxon-property x_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return ChartSeries
     */
    public function setXAttributeAlias(string $alias) : ChartSeries
    {
        $this->xAttributeAlias = $alias;
        return $this;
    }
    
    /**
     * 
     * @return ChartAxis
     */
    public function getYAxis() : ChartAxis
    {
        return $this->yAxis;
    }
    
    /**
     * Use this attribute for Y-values
     * 
     * @uxon-property y_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return ChartSeries
     */
    public function setYAttributeAlias(string $alias) : ChartSeries
    {
        $this->yAttributeAlias = $alias;
        return $this;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see ChartSeries::getValueDataColumn()
     */
    public function getValueDataColumn(): DataColumn
    {
        if ($this->getValueColumnDimension() === chart::AXIS_X) {
            return $this->getXDataColumn();
        } else {
            return $this->getYDataColumn();
        }
    }
    
    /**
     * Alias of the attribute to display on the value axis.
     * 
     * Which axis is the value axis, depends on the chart type. This property
     * allows to bind the value regardless of the axis dimension, making it
     * easier to switch chart types (you don't need to chang `x_attribute_alias`
     * to `y_attribute_alias` or vice versa).
     * 
     * @uxon-property value_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return ChartSeries
     */
    public function setValueAttributeAlias(string $alias) : ChartSeries
    {
        if ($this->getValueColumnDimension() === chart::AXIS_X) {
            return $this->setXAttributeAlias($alias);
        } else {
            return $this->setYAttributeAlias($alias);
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function getValueColumnDimension() : string
    {
        return Chart::AXIS_Y;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getYDataColumn() : DataColumn
    {
        if ($this->yColumn === null && $this->yAxis !== null) {
            $this->yColumn = $this->yAxis->getDataColumn();
        }
        return $this->yColumn;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getXDataColumn() : DataColumn
    {
        if ($this->xColumn === null && $this->xAxis !== null) {
            $this->xColumn = $this->xAxis->getDataColumn();
        }
        return $this->xColumn;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::prepareDataWidget()
     */
    public function prepareDataWidget(iShowData $dataWidget): ChartSeries
    {
        $this->xColumn = null;
        $this->yColumn = null;
        $this->xAxis = null;
        $this->yAxis = null;
        
        // Find X-column
        if ($this->xColumnId !== null) {
            $this->xColumn = $dataWidget->getColumn($this->xColumnId);
        } elseif ($this->xAttributeAlias !== null) {
            if ($col = $dataWidget->getColumnByAttributeAlias($this->xAttributeAlias)) {
                $this->xColumn = $col;
            } else {
                $col = $dataWidget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->xAttributeAlias
                ]));
                $this->xColumn = $col;
                $dataWidget->addColumn($col);
            }
        } 
        
        // Find Y-column
        if ($this->yColumnId !== null) {
            $this->yColumn = $dataWidget->getColumn($this->yColumnId);
        } elseif ($this->yAttributeAlias !== null) {
            if ($col = $dataWidget->getColumnByAttributeAlias($this->yAttributeAlias)) {
                $this->yColumn = $col;
            } else {
                $col = $dataWidget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->yAttributeAlias
                ]));
                $this->yColumn = $col;
                $dataWidget->addColumn($col);
            }
        } 
        
        // Find X-axis
        if (! $axis = $this->findAxis(Chart::AXIS_X)) {
            throw new WidgetConfigurationError($this->getChart(), 'Cannot find X-axis for series ' . $this->getIndex() . ' of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
        }
        $this->xAxis = $axis;
        
        // Find Y-axis
        if (! $axis = $this->findAxis(Chart::AXIS_Y)) {
            throw new WidgetConfigurationError($this->getChart(), 'Cannot find Y-axis for series ' . $this->getIndex() . ' of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
        }
        $this->yAxis = $axis;
        
        return $this;
    }
    
    protected function findAxis(string $dimension) : ?ChartAxis
    {
        $axis = null;
        $axisNo = $dimension === Chart::AXIS_X ? $this->getXAxisNo() : $this->getYAxisNo();
        $attributeAlias = $dimension === Chart::AXIS_X ? $this->xAttributeAlias : $this->yAttributeAlias;
        $column = $dimension === Chart::AXIS_X ? $this->xColumn : $this->yColumn;
        $columnId = $dimension === Chart::AXIS_X ? $this->xColumnId : $this->yColumnId;
        $secondaryAxisPosition = $dimension === Chart::AXIS_X ? 'bottom' : 'right';
        $chart = $this->getChart();
        
        //Check if series is stacked and if so find previous series with same stack and use axis from that series
        if ($this instanceof StackableChartSeriesInterface && $this->isStacked() && $this->getValueColumnDimension() === $dimension && $this->getIndex() > 0) {
            $prevSeries = $this->getChart()->getSeries()[($this->getIndex() - 1)];
            if ($prevSeries instanceof StackableChartSeriesInterface && $prevSeries->getStackGroupId() === $this->getStackGroupId()) {
                return $dimension === Chart::AXIS_X ? $prevSeries->getXAxis() : $prevSeries->getYAxis();
            }
        }
        
        //when series has number given, try get axis with that number, if that fails, continue
        if ($axisNo !== null) {
            try {
                $axis = $chart->getAxes($dimension)[$axisNo];
                if ($axis !== null) {
                    return $axis;
                }
            } catch (\Throwable $e) {
                // Continue with the other cases
            }
        }
        
        switch (true) {
            //series has attribute_alias set
            case $attributeAlias !== null:
                $existingAxes = $chart->getAxes($dimension);
                switch (count($existingAxes)) {
                    //when no axis already exists create new axis
                    case 0:
                        $axis = $chart->createAxisFromColumnId($column->getId());
                        $chart->addAxis($dimension, $axis);
                        break;
                    //when there are already axes existing, search if one has same attribute
                    default:
                        $attr = $this->getMetaObject()->getAttribute($attributeAlias);
                        $attrAxes = $chart->findAxesByAttribute($attr, $dimension);
                        //when there is no axis with same attribute, create new axis
                        if (empty($attrAxes)) {
                            $axis = $chart->createAxisFromColumnId($column->getId());
                            $axis->setPosition($secondaryAxisPosition);
                            $chart->addAxis($dimension, $axis);
                        //when there are axes with same attribute, use first of those axes
                        } else {
                            $axis = $attrAxes[0];
                        }
                        
                }
                break;
            //when no attribute_alias or axisNo given, check if already axes exist and if so, take the first
            case empty($chart->getAxes($dimension)) === false:
                $axis = $chart->getAxes($dimension)[0];
                break;
            //when columnId given, create axis base on that columnId
            case $columnId !== null:
                $axis = $chart->createAxisFromColumnId($columnId);
                $chart->addAxis($dimension, $axis);
                break;
        }
        return $axis;
    }
    
    /**
     *
     * @return int
     */
    protected function getXAxisNo() : ?int
    {
        return $this->xAxisNo;
    }
    
    /**
     * The index of the X-axis to use (instead of an creating a new X-axis)
     * 
     * @uxon-property x_axis_no
     * @uxon-type integer
     * @uxon-template 0
     * 
     * @param int $value
     * @return ChartSeries
     */
    public function setXAxisNo(int $value) : ChartSeries
    {
        $this->xAxisNo = $value;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    protected function getYAxisNo() : ?int
    {
        return $this->yAxisNo;
    }
    
    /**
     * The index of the Y-axis to use (instead of an creating a new Y-axis)
     * 
     * @uxon-property y_axis_no
     * @uxon-type integer
     * @uxon-template 0
     * 
     * @param int $value
     * @return ChartSeries
     */
    public function setYAxisNo(int $value) : ChartSeries
    {
        $this->yAxisNo = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getXColumnId() : ?string
    {
        return $this->xColumnId;
    }
    
    /**
     * The id of the column in the data widget to use for X-values.
     * 
     * @uxon-property x_column_id
     * @uxon-type uxon:$..id
     * 
     * @param string $value
     * @return ChartSeries
     */
    public function setXColumnId(string $value) : ChartSeries
    {
        $this->xColumnId = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getYColumnId() : ?string
    {
        return $this->yColumnId;
    }
    
    /**
     * The id of the column in the data widget to use for Y-values.
     * 
     * @uxon-property y_column_id
     * @uxon-type uxon:$..id
     * 
     * @param string $value
     * @return ChartSeries
     */
    public function setYColumnId(string $value) : ChartSeries
    {
        $this->yColumnId = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isXBoundToAttribute() : bool
    {
        return $this->xAttributeAlias !== null;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isYBoundToAttribute() : bool
    {
        return $this->yAttributeAlias !== null;
    }
    
    public function getValueAxis() : ChartAxis
    {
        return $this->getValueColumnDimension() === Chart::AXIS_X ? $this->getXAxis() : $this->getYAxis();
    }
}