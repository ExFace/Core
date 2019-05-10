<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Model\MetaAttributeInterface;

class PieChart extends AbstractChartType
{
    private $value_attribute_alias = null;
    
    private $value_axis = null;
    
    private $text_attribute_alias = null;
    
    private $text_axis = null;
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    protected function getValueAttribute() : MetaAttributeInterface
    {
        return $this->getChartSeries()->getMetaObject()->getAttribute($this->value_attribute_alias);
    }
    
    /**
     * Attribute alias for segment values (relative to the meta object of the chart series).
     * 
     * @uxon-property value_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $aliasRelativeToSeriesObject
     * @return PieChart
     */
    public function setValueAttributeAlias(string $aliasRelativeToSeriesObject) : PieChart
    {
        $this->value_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    public function isValueBoundToAttribute() : bool
    {
        return $this->value_attribute_alias !== null;
    }
    
    public function getValueAxis() : ChartAxis
    {
        if ($this->value_axis === null) {
            $axis = $this->getChart()->findAxisByAttribute($this->getValueAttribute());
            if ($axis === null) {
                $axis = $this->getChart()->createAxisFromAttribute($this->value_attribute_alias);
                $this->getChart()->addAxisY($axis);
            }
            $this->text_axis = $axis;
        }
        return $this->value_axis;
    }
    
    /**
     *
     * @return MetaAttributeInterface
     */
    public function getTextAttribute() : MetaAttributeInterface
    {
        return $this->getChartSeries()->getMetaObject()->getAttribute($this->text_attribute_alias);
    }
    
    /**
     * Attribute alias for the legend (relative to the meta object of the chart series).
     *
     * @uxon-property text_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $aliasRelativeToSeriesObject
     * @return PieChart
     */
    public function setTextAttributeAlias(string $aliasRelativeToSeriesObject) : PieChart
    {
        $this->text_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    public function isTextBoundToAttribute() : bool
    {
        return $this->text_attribute_alias !== null;
    }
    
    public function getTextAxis() : ChartAxis
    {
        if ($this->text_axis === null) {
            $axis = $this->getChart()->findAxisByAttribute($this->getTextAttribute());
            if ($axis === null) {
                $axis = $this->getChart()->createAxisFromAttributeAlias($this->text_attribute_alias);
                $this->getChart()->addAxisX($axis);
            }
            $this->text_axis = $axis;
        }
        return $this->text_axis;
    }
    
    public function getCaption(): string
    {
        return $this->getValueAxis()->getCaption();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\AbstractChartType::prepareAxes()
     */
    public function prepareAxes() : AbstractChartType
    {
        $this->getTextAxis();
        $this->getValueAxis();
        return $this;
    }
}