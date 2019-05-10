<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Chart;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Uxon\UxonSchema;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractChartPart implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $chart = null;
    
    private $workbench = null;
    
    public function __construct(Chart $widget, UxonObject $uxon = null)
    {
        $this->chart = $widget;
        $this->workbench = $widget->getWorkbench();
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
        return $this->chart;
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
}