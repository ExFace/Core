<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iShowData;

/**
 * Pie chart using a meta attribute for values and another one for labels.
 * 
 * This example shows which of installed apps have the most objects:
 * 
 * ```
 * {
 *  "widget_type": "Chart",
 *  "object_alias": "exface.Core.APP"
 *  "series": [
 *      "type": "pie",
 *      "value_attribute_alias": "OBJECT__UID:COUNT",
 *      "text_attribute_alias: "LABEL"
 *  ]
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class PieChartSeries extends ChartSeries
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
     * @return bool
     */
    public function isTextBoundToAttribute() : bool
    {
        return $this->text_attribute_alias !== null;
    }
    
    /**
     * 
     * @return ChartAxis
     */
    public function getTextAxis() : ChartAxis
    {
        if ($this->text_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getTextAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->text_attribute_alias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->text_axis = $axis;
        }
        return $this->text_axis;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getTextDataColumn() : DataColumn
    {
        return $this->getTextAxis()->getDataColumn();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::prepareAxes()
     */
    public function prepareDataWidget(iShowData $dataWidget) : ChartSeries
    {
        $this->getTextAxis();
        $this->getValueAxis();
        return $this;
    }
}