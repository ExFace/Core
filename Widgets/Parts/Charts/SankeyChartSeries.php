<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

class SankeyChartSeries extends ChartSeries
{
    private $sourceAttributeAlias = null;
    
    private $sourceAttributeAxis = null;
    
    private $sourceNameAttributeAlias = null;
    
    private $sourceNameAttributeAxis = null;
    
    private $targetAttributeAlias = null;
    
    private $targetAttributeAxis = null;
    
    private $targetNameAttributeAlias = null;
    
    private $targetNameAttributeAxis = null;
    
    private $linkNameAttributeAlias = null;
    
    private $linkNameAttributeAxis = null;
    
    private $levelAttributeAlias = null;
    
    private $levelAttributeAxis = null;
    
    
    /**
     * Attribute alias for source of sankey connection (relative to the meta object of the chart series).
     * 
     * @uxon-property source_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * 
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setSourceAttributeAlias(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->sourceAttributeAlias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the source of a sankey connection
     * 
     * @return string
     */
    public function getSourceAttributeAlias() : string
    {
        return $this->sourceAttributeAlias;
    }
    
    /**
     * Returns the attribute of the source of a sankey connection
     * 
     * @return MetaAttributeInterface
     */
    public function getSourceAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->sourceAttributeAlias);
    }
    
    /**
     * get the axis for the source attribute
     *
     * @return ChartAxis
     */
    public function getSourceAttributeAxis() : ChartAxis
    {
        if ($this->sourceAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getSourceAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->sourceAttributeAlias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->sourceAttributeAxis = $axis;
        }
        return $this->sourceAttributeAxis;
    }
    
    /**
     * get Data Column for source attribute
     *
     * @return DataColumn
     */
    public function getSourceAttributeDataColumn() : DataColumn
    {
        return $this->getSourceAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for source name of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property source_name_attribute_alias
     * @uxon-type metamodel:attribute
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setSourceNameAttributeAlias(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->sourceNameAttributeAlias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the source name of a sankey connection
     *
     * @return string
     */
    public function getSourceNameAttributeAlias() : string
    {
        return $this->sourceNameAttributeAlias;
    }
    
    /**
     * Returns the attribute of the source name of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getSourcenameAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->sourceNameAttributeAlias);
    }
    
    /**
     * get the axis for the source attribute
     *
     * @return ChartAxis
     */
    public function getSourceNameAttributeAxis() : ChartAxis
    {
        if ($this->sourceNameAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getSourceNameAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->sourceNameAttributeAlias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->sourceNameAttributeAxis = $axis;
        }
        return $this->sourceNameAttributeAxis;
    }
    
    /**
     * get Data Column for source name attribute
     *
     * @return DataColumn
     */
    public function getSourceNameAttributeDataColumn() : DataColumn
    {
        return $this->getSourceNameAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for target of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property target_attribute_alias
     * @uxon-type metamodel:attribute
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setTargetAttributeAlias(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->targetAttributeAlias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the target of a sankey connection
     *
     * @return string
     */
    public function getTargetAttributeAlias() : string
    {
        return $this->targetAttributeAlias;
    }
    
    /**
     * Returns the attribute of the target of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getTargetAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->targetAttributeAlias);
    }
    
    /**
     * get the axis for the target attribute
     *
     * @return ChartAxis
     */
    public function getTargetAttributeAxis() : ChartAxis
    {
        if ($this->targetAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getTargetAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->targetAttributeAlias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->targetAttributeAxis = $axis;
        }
        return $this->targetAttributeAxis;
    }
    
    /**
     * get Data Column for target attribute
     *
     * @return DataColumn
     */
    public function getTargetAttributeDataColumn() : DataColumn
    {
        return $this->getTargetAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for target name of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property target_name_attribute_alias
     * @uxon-type metamodel:attribute
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setTargetNameAttributeAlias(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->targetNameAttributeAlias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the target name of a sankey connection
     *
     * @return string
     */
    public function getTargetNameAttributeAlias() : string
    {
        return $this->targetNameAttributeAlias;
    }
    
    /**
     * Returns the attribute of the target name of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getTargetNameAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->targetNameAttributeAlias);
    }
    
    /**
     * get the axis for the target name attribute
     *
     * @return ChartAxis
     */
    public function getTargetNameAttributeAxis() : ChartAxis
    {
        if ($this->targetNameAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getTargetAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->targetNameAttributeAlias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->targetNameAttributeAxis = $axis;
        }
        return $this->targetNameAttributeAxis;
    }
    
    /**
     * get Data Column for target name attribute
     *
     * @return DataColumn
     */
    public function getTargetNameAttributeDataColumn() : DataColumn
    {
        return $this->getTargetNameAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for link name of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property link_name_attribute_alias
     * @uxon-type metamodel:attribute
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setLinkNameAttributeAlias(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->linkNameAttributeAlias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the link of a sankey connection
     *
     * @return string
     */
    public function getLinkNameAttributeAlias() : string
    {
        return $this->linkNameAttributeAlias;
    }
    
    /**
     * Returns the attribute of the link name of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getLinkNameAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->linkNameAttributeAlias);
    }
    
    /**
     * get the axis for the link attribute
     *
     * @return ChartAxis
     */
    public function getLinkNameAttributeAxis() : ChartAxis
    {
        if ($this->linkNameAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getLinkAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->linkNameAttributeAlias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->linkNameAttributeAxis = $axis;
        }
        return $this->linkNameAttributeAxis;
    }
    
    /**
     * get Data Column for link attribute
     *
     * @return DataColumn
     */
    public function getLinkNameAttributeDataColumn() : DataColumn
    {
        return $this->getLinkNameAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for levels of a sankey chart (relative to the meta object of the chart series).
     *
     * @uxon-property level_attribute_alias
     * @uxon-type metamodel:attribute
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setLevelAttributeAlias(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->levelAttributeAlias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the level of a sankey chart
     *
     * @return string
     */
    public function getLevelAttributeAlias() : string
    {
        return $this->linkNameAttributeAlias;
    }
    
    /**
     * Returns the attribute of the levels of a sankey chart
     *
     * @return MetaAttributeInterface
     */
    public function getLevelAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->levelAttributeAlias);
    }
    
    /**
     * get the axis for the levels attribute
     *
     * @return ChartAxis
     */
    public function getLevelAttributeAxis() : ChartAxis
    {
        if ($this->levelAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getLevelAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->levelAttributeAlias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->levelAttributeAxis = $axis;
        }
        return $this->levelAttributeAxis;
    }
    
    /**
     * get Data Column for level attribute
     *
     * @return DataColumn
     */
    public function getLevelAttributeDataColumn() : DataColumn
    {
        return $this->getLevelAttributeAxis()->getDataColumn();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::getValueDataColumn()
     */
    public function getValueDataColumn() : DataColumn
    {
        return $this->getLeftObjectAxis()->getDataColumn();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::getValueAxis()
     */
    public function getValueAxis() : ChartAxis
    {
        return $this->getSourceAttributeAxis();
    }

    public function prepareDataWidget(iShowData $dataWidget): ChartSeries
    {
        $this->getSourceAttributeAxis();
        $this->getSourceNameAttributeAxis();
        $this->getTargetAttributeAxis();
        $this->getTargetNameAttributeAxis();
        $this->getLinkNameAttributeAxis();
        if ($this->hasLevelDataColumn()) {
            $this->getLevelAttributeAxis();
        }
    }

    
}