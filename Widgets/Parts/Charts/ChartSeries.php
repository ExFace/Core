<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Chart;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class ChartSeries extends AbstractChartPart implements iHaveCaption
{
    use ImportUxonObjectTrait;
    use iHaveCaptionTrait {
        getCaption as getCaptionViaTrait;
    }
    
    private $type = null;
    
    public function __construct(Chart $widget, UxonObject $uxon = null, string $seriesType = null)
    {
        parent::__construct($widget, $uxon);
        if ($seriesType !== null) {
            $this->type = $seriesType;
        }
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
     * @uxon-property type
     * @uxon-type [line, spline, area, pie, donut, bar, column, rose]
     * @uxon-required true
     * 
     * @param string $value
     * @return ChartSeries
     */
    public function setType(string $value) : ChartSeries
    {
        $this->type = $value;
        return $this;
    }
    
    public function getType() : string
    {
        return $this->type;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption()
    {
        if ($this->getCaptionViaTrait() === null) {
            $this->setCaption($this->getValueDataColumn()->getCaption());
        }
        return $this->getCaptionViaTrait();
    }
    
    public function getIndex() : int
    {
        return $this->getChart()->getSeriesIndex($this);
    }
    
    public static function getUxonSchemaClass() : ?string
    {
        return ChartSeriesUxonSchema::class;
    }
    
    abstract public function getValueDataColumn() : DataColumn;
    
    abstract public function prepareDataWidget(iShowData $dataWidget) : ChartSeries;
}