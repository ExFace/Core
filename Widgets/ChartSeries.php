<?php

namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * The ChartSeries represents a single series in a chart (e.g.
 * line of a line chart).
 *
 * Most important options of ChartSeries are the chart type (line, bars, columns, etc.) and the data_column_id to fetch the values from.
 *
 * For simple charts, you do not need to specify each series separately - simply add the desired "chart_type" to the axis
 * with the corresponding data_column_id.
 *
 * The ChartSeries widget can only be used within a Chart.
 *
 * @author Andrej Kabachnik
 *        
 */
class ChartSeries extends AbstractWidget
{

    const CHART_TYPE_LINE = 'line';

    const CHART_TYPE_BARS = 'bars';

    const CHART_TYPE_COLUMNS = 'columns';

    const CHART_TYPE_AREA = 'area';

    const CHART_TYPE_PIE = 'pie';

    private $chart_type = null;

    private $series_number = null;

    private $data_column_id = null;

    private $axis_x_number = null;

    private $axis_x = null;

    private $axis_y_number = null;

    private $axis_y = null;

    /**
     *
     * @return DataColumn
     */
    public function getDataColumn()
    {
        if (! $result = $this->getChart()
            ->getData()
            ->getColumn($this->getDataColumnId())) {
            $result = $this->getChart()
                ->getData()
                ->getColumnByAttributeAlias($this->getDataColumnId());
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
        return $this;
    }

    public function getChartType()
    {
        return $this->chart_type;
    }

    /**
     * Sets the visualization type for this series: line, bars, columns, pie or area.
     *
     * @uxon-property chart_type
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\Widgets\ChartSeries
     */
    public function setChartType($value)
    {
        $this->chart_type = strtolower($value);
        return $this;
    }

    public function getDataColumnId()
    {
        return $this->data_column_id;
    }

    /**
     * Defines the column in the chart's data, that will provide the values of this series.
     *
     * @uxon-property data_column_id
     * @uxon-type string
     *
     * @param string $value            
     * @return ChartSeries
     */
    public function setDataColumnId($value)
    {
        $this->data_column_id = $value;
        return $this;
    }

    public function getAxisX()
    {
        if (is_null($this->axis_x)) {
            $axis = $this->getChart()->findAxisByColumnId($this->getDataColumnId(), Chart::AXIS_X);
            if (! $axis) {
                $axis = $this->getChart()->getAxesX()[0];
            }
            if (! $axis) {
                throw new WidgetConfigurationError($this, 'Cannot find x-axis for series "' . $this->getId() . '" of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
            }
            $this->axis_x = $axis;
        }
        return $this->axis_x;
    }

    public function setAxisX(ChartAxis $axis)
    {
        $this->axis_x = $axis;
    }

    public function getAxisXNumber()
    {
        if (is_null($this->axis_x_number) && $this->getAxisX()) {
            return $this->getAxisX()->getNumber();
        }
        return $this->axis_x_number;
    }

    /**
     * Makes the series use the specified X-axis: e.g.
     * axis_x_number = 2 will make the X-values appear on the second X-axis.
     *
     * @uxon-property axis_x_number
     * @uxon-type string
     *
     * @param integer $number            
     * @return \exface\Core\Widgets\ChartSeries
     */
    public function setAxisXNumber($number)
    {
        $this->axis_x_number = $number;
        return $this;
    }

    public function getAxisY()
    {
        if (is_null($this->axis_y)) {
            $axis = $this->getChart()->findAxisByColumnId($this->getDataColumnId(), Chart::AXIS_Y);
            if (! $axis) {
                $axis = $this->getChart()->getAxesY()[0];
            }
            if (! $axis) {
                throw new WidgetConfigurationError($this, 'Cannot find y-axis for series "' . $this->getChartType() . '" of widget "' . $this->getChart()->getId() . '"!', '6T90UV9');
            }
            $this->axis_y = $axis;
        }
        return $this->axis_y;
    }

    public function setAxisY(ChartAxis $axis)
    {
        $this->axis_y = $axis;
        return $this;
    }

    public function getAxisYNumber()
    {
        if (is_null($this->axis_y_number) && $this->getAxisY()) {
            return $this->getAxesY()->getId();
        }
        return $this->axis_y_number;
    }

    /**
     * Makes the series use the specified Y-axis: e.g.
     * axis_x_number = 2 will make the Y-values appear on the second Y-axis.
     *
     * @uxon-property axis_y_number
     * @uxon-type string
     *
     * @param integer $number            
     * @return \exface\Core\Widgets\ChartSeries
     */
    public function setAxisYNumber($number)
    {
        $this->axis_y_number = $number;
        return $this;
    }

    /**
     * The caption for a series can either be set directly, or will be inherited from the used data column.
     *
     * @uxon-property caption
     * @uxon-type string
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

    public function getSeriesNumber()
    {
        if (is_null($this->series_number)) {
            foreach ($this->getChart()->getSeries() as $n => $s) {
                if ($s->getId() == $this->getId()) {
                    $this->series_number = $n;
                }
            }
        }
        return $this->series_number;
    }

    public function setSeriesNumber($value)
    {
        $this->series_number = $value;
        return $this;
    }
}
?>