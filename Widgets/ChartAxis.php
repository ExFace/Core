<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * The ChartAxis represents the X or Y axis of a chart.
 *
 * Most important properties of a ChartAxis are it's caption, type (time, text, numbers, etc.), position and
 * min/max values. An axis can also be hidden.
 *
 * The ChartSeries widget can only be used within a Chart.
 *
 * @author Andrej Kabachnik
 *        
 */
class ChartAxis extends AbstractWidget
{

    private $number = null;

    private $dimension = null;

    private $axis_type = null;

    private $data_column_id = null;

    private $min_value = null;

    private $max_value = null;

    private $position = null;

    const POSITION_TOP = 'TOP';

    const POSITION_RIGHT = 'RIGHT';

    const POSITION_BOTTOM = 'BOTTOM';

    const POSITION_LEFT = 'LEFT';

    const AXIS_TYPE_TIME = 'TIME';

    const AXIS_TYPE_TEXT = 'TEXT';

    const AXIS_TYPE_NUMBER = 'NUMBER';

    /**
     *
     * @return DataColumn
     */
    public function getDataColumn()
    {
        if (! $result = $this->getChart()->getData()->getColumn($this->getDataColumnId())) {
            $result = $this->getChart()->getData()->getColumnByAttributeAlias($this->getDataColumnId());
        }
        return $result;
    }

    /**
     *
     * @return Chart
     */
    public function getChart()
    {
        return $this->getParent();
    }

    public function setChart(Chart $widget)
    {
        $this->chart = $this->setParent($widget);
    }

    /**
     * Creates a chart series from the data of this axis.
     * It's a shortcut instead of a full series definition.
     *
     * @uxon-property chart_type
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\Widgets\ChartAxis
     */
    public function setChartType($value)
    {
        $series = $this->getChart()->createSeries($value);
        switch ($value) {
            case ChartSeries::CHART_TYPE_BARS:
                $series->setAxisX($this);
                break;
            default:
                $series->setAxisY($this);
        }
        $series->setDataColumnId($this->getDataColumnId());
        $this->getChart()->addSeries($series);
        return $this;
    }

    public function getDataColumnId()
    {
        return $this->data_column_id;
    }

    /**
     * Specifies the data column to use for values of this axis by the column's id.
     *
     * @uxon-property data_column_id
     * @uxon-type string
     *
     * @param string $value            
     */
    public function setDataColumnId($value)
    {
        $this->data_column_id = $value;
    }

    public function getMinValue()
    {
        return $this->min_value;
    }

    /**
     * Sets the minimum value for the scale of this axis.
     * If not set, the minimum value of the underlying data will be used.
     *
     * @uxon-property min_value
     * @uxon-type number
     *
     * @param float $value            
     */
    public function setMinValue($value)
    {
        $this->min_value = $value;
    }

    public function getMaxValue()
    {
        return $this->max_value;
    }

    /**
     * Sets the maximum value for the scale of this axis.
     * If not set, the maximum value of the underlying data will be used.
     *
     * @uxon-property max_value
     * @uxon-type number
     *
     * @param float $value            
     */
    public function setMaxValue($value)
    {
        $this->max_value = $value;
    }

    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Defines the position of the axis on the chart: LEFT/RIGHT for Y-axes and TOP/BOTTOM for X-axes.
     *
     * @uxon-property position
     * @uxon-type string
     *
     * @param string $value            
     * @return ChartAxis
     */
    public function setPosition($value)
    {
        $value = mb_strtoupper($value);
        if (defined('\\exface\\Core\\Widgets\\ChartAxis::POSITION_' . $value)) {
            $this->position = $value;
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid axis position "' . $value . '". Only TOP, RIGHT, BOTTOM or LEFT allowed!', '6TA2Y6A');
        }
        return $this;
    }

    public function getAxisType()
    {
        return $this->axis_type;
    }

    /**
     * Sets the type of the axis: TIME, TEXT or NUMBER.
     *
     * @uxon-property axis_type
     * @uxon-type string
     *
     * @param string $value            
     * @return ChartAxis
     */
    public function setAxisType($value)
    {
        $value = mb_strtoupper($value);
        if (defined('\\exface\\Core\\Widgets\\ChartAxis::AXIS_TYPE_' . $value)) {
            $this->axis_type = $value;
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid axis type "' . $value . '". Only TIME, TEXT or NUMBER allowed!', '6TA2Y6A');
        }
        return $this;
    }

    public function getDimension()
    {
        return $this->dimension;
    }

    public function setDimension($x_or_y)
    {
        $this->dimension = $x_or_y;
        return $this;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function setNumber($value)
    {
        $this->number = $value;
        return $this;
    }

    /**
     * The caption for an axis can either be set directly, or will be inherited from the used data column
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getCaption()
     */
    public function getCaption()
    {
        if (is_null(parent::getCaption())) {
            parent::setCaption($this->getDataColumn()->getCaption());
        }
        return parent::getCaption();
    }
}
?>