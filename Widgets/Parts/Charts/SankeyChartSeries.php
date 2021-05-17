<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\Chart;

class SankeyChartSeries extends ChartSeries
{
    private $sourceIdAttribute = null;
    
    private $sourceIdAttributeAxis = null;
    
    private $sourceCaptionAttribute = null;
    
    private $sourceCaptionAttributeAxis = null;
    
    private $sourceLevelAttribute = null;
    
    private $sourceLevelAttributeAxis = null;
    
    private $targetIdAttribute = null;
    
    private $targetIdAttributeAxis = null;
    
    private $targetCaptionAttribute = null;
    
    private $targetCaptionAttributeAxis = null;    
    
    private $targetLevelAttribute = null;
    
    private $targetLevelAttributeAxis = null;
    
    private $linkCaptionAttribute = null;
    
    private $linkCaptionAttributeAxis = null;
    
    
    /**
     * Attribute alias for source of sankey connection (relative to the meta object of the chart series).
     * 
     * @uxon-property source_id_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * 
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setSourceIdAttribute(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->sourceIdAttribute = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the source of a sankey connection
     * 
     * @return string
     */
    public function getSourceIdAttributeAlias() : string
    {
        return $this->sourceIdAttribute;
    }
    
    /**
     * Returns the attribute of the source of a sankey connection
     * 
     * @return MetaAttributeInterface
     */
    public function getSourceIdAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->sourceIdAttribute);
    }
    
    /**
     * get the axis for the source attribute
     *
     * @return ChartAxis
     */
    public function getSourceIdAttributeAxis() : ChartAxis
    {
        if ($this->sourceIdAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getSourceIdAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->sourceIdAttribute);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->sourceIdAttributeAxis = $axis;
        }
        return $this->sourceIdAttributeAxis;
    }
    
    /**
     * get Data Column for source attribute
     *
     * @return DataColumn
     */
    public function getSourceIdAttributeDataColumn() : DataColumn
    {
        return $this->getSourceIdAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for source caption of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property source_caption_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setSourceCaptionAttribute(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->sourceCaptionAttribute = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the source caption of a sankey connection
     *
     * @return string
     */
    public function getSourceCaptionAttributeAlias() : string
    {
        return $this->sourceCaptionAttribute;
    }
    
    /**
     * Returns the attribute of the source caption of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getSourceCaptionAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->sourceCaptionAttribute);
    }
    
    /**
     * get the axis for the source caption attribute
     *
     * @return ChartAxis
     */
    public function getSourceCaptionAttributeAxis() : ChartAxis
    {
        if ($this->sourceCaptionAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getSourceCaptionAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->sourceCaptionAttribute);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->sourceCaptionAttributeAxis = $axis;
        }
        return $this->sourceCaptionAttributeAxis;
    }
    
    /**
     * get Data Column for source caption attribute
     *
     * @return DataColumn
     */
    public function getSourceCaptionAttributeDataColumn() : DataColumn
    {
        return $this->getSourceCaptionAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for source level of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property source_level_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setSourceLevelAttribute(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->sourceLevelAttribute = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the source level of a sankey connection
     *
     * @return string
     */
    public function getSourceLevelAttributeAlias() : string
    {
        return $this->sourceLevelAttribute;
    }
    
    /**
     * Returns the attribute of the source level of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getSourceLevelAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->sourceLevelAttribute);
    }
    
    /**
     * get the axis for the source level attribute
     *
     * @return ChartAxis
     */
    public function getSourceLevelAttributeAxis() : ChartAxis
    {
        if ($this->sourceLevelAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getSourceLevelAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->sourceLevelAttribute);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->sourceLevelAttributeAxis = $axis;
        }
        return $this->sourceLevelAttributeAxis;
    }
    
    /**
     * get Data Column for source level attribute
     *
     * @return DataColumn
     */
    public function getSourceLevelAttributeDataColumn() : DataColumn
    {
        return $this->getSourceLevelAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute for target id of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property target_id_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setTargetIdAttribute(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->targetIdAttribute = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the target id of a sankey connection
     *
     * @return string
     */
    public function getTargetIdAttributeAlias() : string
    {
        return $this->targetIdAttribute;
    }
    
    /**
     * Returns the attribute of the target id of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getTargetIdAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->targetIdAttribute);
    }
    
    /**
     * get the axis for the target id attribute
     *
     * @return ChartAxis
     */
    public function getTargetIdAttributeAxis() : ChartAxis
    {
        if ($this->targetIdAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getTargetIdAttribute(), Chart::AXIS_Y);
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->targetIdAttribute);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->targetIdAttributeAxis = $axis;
        }
        return $this->targetIdAttributeAxis;
    }
    
    /**
     * get Data Column for target attribute
     *
     * @return DataColumn
     */
    public function getTargetIdAttributeDataColumn() : DataColumn
    {
        return $this->getTargetIdAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for target caption of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property target_caption_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setTargetCaptionAttribute(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->targetCaptionAttribute = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the target caption of a sankey connection
     *
     * @return string
     */
    public function getTargetCaptionAttributeAlias() : string
    {
        return $this->targetCaptionAttribute;
    }
    
    /**
     * Returns the attribute of the target caption of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getTargetCaptionAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->targetCaptionAttribute);
    }
    
    /**
     * get the axis for the target caption attribute
     *
     * @return ChartAxis
     */
    public function getTargetCaptionAttributeAxis() : ChartAxis
    {
        if ($this->targetCaptionAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getTargetCaptionAttribute(), Chart::AXIS_Y);
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->targetCaptionAttribute);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->targetCaptionAttributeAxis = $axis;
        }
        return $this->targetCaptionAttributeAxis;
    }
    
    /**
     * get Data Column for target caption attribute
     *
     * @return DataColumn
     */
    public function getTargetCaptionAttributeDataColumn() : DataColumn
    {
        return $this->getTargetCaptionAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for target level of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property target_level_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setTargetLevelAttribute(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->targetLevelAttribute = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the target level of a sankey connection
     *
     * @return string
     */
    public function getTargetLevelAttributeAlias() : string
    {
        return $this->targetLevelAttribute;
    }
    
    /**
     * Returns the attribute of the target level of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getTargetLevelAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->targetLevelAttribute);
    }
    
    /**
     * get the axis for the target level attribute
     *
     * @return ChartAxis
     */
    public function getTargetLevelAttributeAxis() : ChartAxis
    {
        if ($this->targetLevelAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getTargetLevelAttribute(), Chart::AXIS_Y);
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->targetLevelAttribute);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->targetLevelAttributeAxis = $axis;
        }
        return $this->targetLevelAttributeAxis;
    }
    
    /**
     * get Data Column for target level attribute
     *
     * @return DataColumn
     */
    public function getTargetLevelAttributeDataColumn() : DataColumn
    {
        return $this->getTargetLevelAttributeAxis()->getDataColumn();
    }
    
    /**
     * Attribute alias for link caption of sankey connection (relative to the meta object of the chart series).
     *
     * @uxon-property link_caption_attribute
     * @uxon-type metamodel:attribute
     * 
     *
     * @param string $aliasRelativeToSeriesObject
     * @return SankeyChartSeries
     */
    public function setLinkCaptionAttribute(string $aliasRelativeToSeriesObject) : SankeyChartSeries
    {
        $this->linkCaptionAttribute = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * Returns the attribute alias of the link caption of a sankey connection
     *
     * @return string
     */
    public function getLinkCaptionAttributeAlias() : string
    {
        return $this->linkCaptionAttribute;
    }
    
    /**
     * Returns the attribute of the link caption of a sankey connection
     *
     * @return MetaAttributeInterface
     */
    public function getLinkCaptionAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->linkCaptionAttribute);
    }
    
    /**
     * get the axis for the link caption attribute
     *
     * @return ChartAxis
     */
    public function getLinkCaptionAttributeAxis() : ChartAxis
    {
        if ($this->linkCaptionAttributeAxis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getLinkCaptionAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->linkCaptionAttribute);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->linkCaptionAttributeAxis = $axis;
        }
        return $this->linkCaptionAttributeAxis;
    }
    
    /**
     * get Data Column for link caption attribute
     *
     * @return DataColumn
     */
    public function getLinkCaptionAttributeDataColumn() : DataColumn
    {
        return $this->getLinkCaptionAttributeAxis()->getDataColumn();
    }  
    
    /**
     * 
     * @return bool
     */
    public function hasLinkCaptionColumn() : bool
    {
        return $this->linkCaptionAttribute !== null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::getValueDataColumn()
     */
    public function getValueDataColumn() : DataColumn
    {
        return $this->getSourceIdAttributeAxis()->getDataColumn();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::getValueAxis()
     */
    public function getValueAxis() : ChartAxis
    {
        return $this->getSourceIdAttributeAxis();
    }

    public function prepareDataWidget(iShowData $dataWidget): ChartSeries
    {
        $this->getSourceIdAttributeAxis();
        $this->getSourceCaptionAttributeAxis();
        $this->getSourceLevelAttributeAxis();
        $this->getTargetIdAttributeAxis();
        $this->getTargetCaptionAttributeAxis();
        $this->getTargetLevelAttributeAxis();
        if ($this->hasLinkCaptionColumn()) {
            $this->getLinkCaptionAttributeAxis();
        }
        return $this;
    }

    
}