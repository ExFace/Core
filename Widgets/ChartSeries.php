<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Charts\AbstractChartType;

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
    private $chart_type = null;

    private $data_column_id = null;

    /**
     *
     * @return Chart
     */
    public function getChart()
    {
        return $this->getParent();
    }

    public function getChartType() : AbstractChartType
    {
        return $this->chart_type;
    }

    /**
     * Sets the visualization type for this series: line, bars, columns, pie or area.
     *
     * @uxon-property chart_type
     * @uxon-type \exface\Core\Widgets\Parts\Charts\AbstractChartType|string
     * @uxon-template {"type": ""}
     *
     * @param UxonObject|string $uxonOrString            
     * @return \exface\Core\Widgets\ChartSeries
     */
    public function setChartType($uxonOrString)
    {
        if ($uxonOrString instanceof UxonObject) {
            $type = mb_strtolower($uxonOrString->getProperty('type'));
            $uxon = $uxonOrString;
        } else {
            $type = mb_strtolower($uxonOrString);
            // compatibility with old chart type names
            switch ($type) {
                case 'bars' : $type = 'bar'; break;
                case 'columns' : $type = 'column'; break;
            }
            $uxon = null;
        }
        $class = '\\exface\\Core\\Widgets\\Parts\\Charts\\' . ucfirst($type) . 'Chart';
        $this->chart_type = new $class($this, $uxon);
        
        return $this;
    }

    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Widgets\AbstractWidget::getCaption()
     */
    public function getCaption()
    {
        return $this->getChartType()->getCaption();
    }
}