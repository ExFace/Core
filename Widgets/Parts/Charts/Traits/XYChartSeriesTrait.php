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
        $axis = null;
        if ($this->getXAxisNo() !== null) {
            $axis = $this->getChart()->getAxesX()[$this->getXAxisNo()];
        } elseif ($this->xAttributeAlias !== null) {
            $attr = $this->getMetaObject()->getAttribute($this->xAttributeAlias);
            $axes = $this->getChart()->findAxesByAttribute($attr, Chart::AXIS_X);
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromColumnId($this->xColumn->getId());
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
        } elseif (empty($this->getChart()->getAxesX()) === false) {
            $axis = $this->getChart()->getAxesX()[0];
        } elseif ($this->getXColumnId() !== null) {
            $axis = $this->getChart()->createAxisFromColumnId($this->getXColumnId());
            $this->getChart()->addAxisX($axis);
        }
        if (! $axis) {
            throw new WidgetConfigurationError($this->getChart(), 'Cannot find X-axis for series ' . $this->getIndex() . ' of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
        }
        $this->xAxis = $axis;
        
        // Find Y-axis
        $axis = null;
        if ($this->getYAxisNo() !== null) {
            $axis = $this->getChart()->getAxesY()[$this->getYAxisNo()];
        } elseif ($this->yAttributeAlias !== null) {
            $attr = $this->getMetaObject()->getAttribute($this->yAttributeAlias);
            $axes = $this->getChart()->findAxesByAttribute($attr, Chart::AXIS_Y);
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromColumnId($this->yColumn->getId());
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
        } elseif (empty($this->getChart()->getAxesY()) === false) {
            $axis = $this->getChart()->getAxesY()[0];
        } elseif ($this->getYColumnId() !== null) {
            $axis = $this->getChart()->createAxisFromColumnId($this->getYColumnId());
            $this->getChart()->addAxisY($axis);
        }
        if (! $axis) {
            throw new WidgetConfigurationError($this->getChart(), 'Cannot find Y-axis for series ' . $this->getIndex() . ' of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
        }
        $this->yAxis = $axis;
        
        return $this;
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
}