<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iShowData;

/**
 * GraphChart to show relations between Objects
 *
 */
class GraphChartSeries extends ChartSeries
{
    private $value_attribute_alias = null;
    
    
    
    private $text_attribute_alias = null;
    

    //TODO Attribute alias für benötigte Daten Spalten (right Object, left Object, relation)
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getValueAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->value_attribute_alias);
    }
    
    /**
     * Attribute alias for segment values (relative to the meta object of the chart series).
     * 
     * @uxon-property value_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $aliasRelativeToSeriesObject
     * @return PieChartSeries
     */
    public function setValueAttributeAlias(string $aliasRelativeToSeriesObject) : PieChartSeries
    {
        $this->value_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isValueBoundToAttribute() : bool
    {
        return $this->value_attribute_alias !== null;
    }
    
    /**
     * 
     * @return ChartAxis
     */
    public function getValueAxis() : ChartAxis
    {
        if ($this->value_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getValueAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->value_attribute_alias);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->value_axis = $axis;
        }
        return $this->value_axis;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::getValueDataColumn()
     */
    public function getValueDataColumn() : DataColumn
    {
        return $this->getValueAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for the legend (relative to the meta object of the chart series).
     *
     * @uxon-property text_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $aliasRelativeToSeriesObject
     * @return PieChartSeries
     */
    public function setTextAttributeAlias(string $aliasRelativeToSeriesObject) : PieChartSeries
    {
        $this->text_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     *
     * @return MetaAttributeInterface
     */
    public function getTextAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->text_attribute_alias);
    } 
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::prepareAxes()
     */
    public function prepareDataWidget(iShowData $dataWidget) : ChartSeries
    {
        return $this;
    }
}