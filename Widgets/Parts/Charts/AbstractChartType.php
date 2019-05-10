<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Chart;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Uxon\UxonSchema;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\ChartSeries;
use exface\Core\Widgets\DataColumn;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractChartType implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $series = null;
    
    private $workbench = null;
    
    private $type = null;
    
    public function __construct(ChartSeries $seriesWidget, UxonObject $uxon = null)
    {
        $this->series = $seriesWidget;
        $this->workbench = $seriesWidget->getWorkbench();
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->getChart();
    }
    
    /**
     * 
     * @return Chart
     */
    public function getChart() : Chart
    {
        return $this->series->getChart();
    }
    
    public function getChartSeries() : ChartSeries
    {
        return $this->series;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : string
    {
        return UxonSchema::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        
        return $uxon;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getDataColumn() : DataColumn
    {
        return $this->series->getDataColumn();
    }
    
    public function setType(string $value) : AbstractChartType
    {
        $this->type = $value;
        return $this;
    }
    
    abstract public function getCaption() : string;
    
    abstract public function prepareAxes() : AbstractChartType;
}