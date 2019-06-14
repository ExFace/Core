<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Chart;

/**
 * GraphChart to show relations between Objects
 *
 */
class GraphChartSeries extends ChartSeries
{
    private $left_object_attribute_alias = null;
    
    private $left_object_name_attribute_alias = null;
    
    private $right_object_attribute_alias = null;
    
    private $right_object_name_attribute_alias = null;
    
    private $relation_attribute_alias = null;
    
    private $relation_name_attribute_alias = null;   
    
    private $left_object_axis = null;
    
    private $left_object_name_axis = null;
    
    private $right_object_axis = null;
    
    private $right_object_name_axis = null;
    
    private $relation_axis = null;
    
    private $relation_name_axis = null;
    
    private $direction_attribute_alias = null;
    
    private $direction_axis = null;
    
    private $graph_type = null;

    //TODO Attribute alias für benötigte Daten Spalten (right Object, left Object, relation)
    /**
     * @uxon-property graph_type
     * @uxon-type string [circular, force]
     * 
     * @param string $type
     * @return GraphChartSeries
     */
    public function setGraphType(string $type) : GraphChartSeries
    {
        $this->graph_type = $type;
        return $this;
    }
    
    public function getGraphType() : ?string
    {
        return $this->graph_type;
    }
    
    /**
     * get the attribute alias for left object
     * 
     * @return MetaAttributeInterface
     */
    public function getLeftObjectAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->left_object_attribute_alias);
    }
    
    /**
     * Attribute alias for left object id (relative to the meta object of the chart series).
     * 
     * @uxon-property left_object_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $aliasRelativeToSeriesObject
     * @return GraphChartSeries
     */
    public function setLeftObjectAttributeAlias(string $aliasRelativeToSeriesObject) : GraphChartSeries
    {
        $this->left_object_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }    
      
    /**
     * get the axis for the left object
     * 
     * @return ChartAxis
     */
    public function getLeftObjectAxis() : ChartAxis
    {
        if ($this->left_object_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getLeftObjectAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->left_object_attribute_alias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->left_object_axis = $axis;
        }
        return $this->left_object_axis;
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
     * get Data Column for Left Object
     *
     * @return DataColumn
     */
    public function getLeftObjectDataColumn() : DataColumn
    {
        return $this->getLeftObjectAxis()->getDataColumn();
    }
    
    /**
     * get the attribute alias for left object name
     * 
     * @return MetaAttributeInterface
     */
    public function getLeftObjectNameAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->left_object_name_attribute_alias);
    }
    
    /**
     * Attribute alias for left object name (relative to the meta object of the chart series).
     * 
     * @uxon-property left_object_name_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $aliasRelativeToSeriesObject
     * @return GraphChartSeries
     */
    public function setLeftObjectNameAttributeAlias(string $aliasRelativeToSeriesObject) : GraphChartSeries
    {
        $this->left_object_name_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }    
      
    /**
     * get the axis for left object name
     * 
     * @return ChartAxis
     */
    public function getLeftObjectNameAxis() : ChartAxis
    {
        if ($this->left_object_name_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getLeftObjectNameAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->left_object_name_attribute_alias);
                $this->getChart()->addAxisX($axis);
            } else {
                $axis = $axes[0];
            }
            $this->left_object_name_axis = $axis;
        }
        return $this->left_object_name_axis;
    }
    
    /**
     * get Data Column for Left Object Name
     *
     * @return DataColumn
     */
    public function getLeftObjectNameDataColumn() : DataColumn
    {
        return $this->getLeftObjectNameAxis()->getDataColumn();
    }
    
    /**
     * get the attribute alias for right object
     *
     * @return MetaAttributeInterface
     */
    public function getRightObjectAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->right_object_attribute_alias);
    }
    
    /**
     * Attribute alias for right object id (relative to the meta object of the chart series).
     *
     * @uxon-property right_object_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $aliasRelativeToSeriesObject
     * @return GraphChartSeries
     */
    public function setRightObjectAttributeAlias(string $aliasRelativeToSeriesObject) : GraphChartSeries
    {
        $this->right_object_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * get the axis for the right object
     *
     * @return ChartAxis
     */
    public function getRightObjectAxis() : ChartAxis
    {
        if ($this->right_object_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getRightObjectAttribute(), Chart::AXIS_Y);
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->right_object_attribute_alias);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->right_object_axis = $axis;
        }
        return $this->right_object_axis;
    }
    
    /**
     * get Data Column for Right Object
     *
     * @return DataColumn
     */
    public function getRightObjectDataColumn() : DataColumn
    {
        return $this->getRightObjectAxis()->getDataColumn();
    }
    
    /**
     * get the attribute alias for right object name
     *
     * @return MetaAttributeInterface
     */
    public function getRightObjectNameAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->right_object_name_attribute_alias);
    }
    
    /**
     * Attribute alias for right object name (relative to the meta object of the chart series).
     *
     * @uxon-property right_object_name_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $aliasRelativeToSeriesObject
     * @return GraphChartSeries
     */
    public function setRightObjectNameAttributeAlias(string $aliasRelativeToSeriesObject) : GraphChartSeries
    {
        $this->right_object_name_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * get the axis for right object name
     *
     * @return ChartAxis
     */
    public function getRightObjectNameAxis() : ChartAxis
    {
        if ($this->right_object_name_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getRightObjectNameAttribute(), Chart::AXIS_Y);
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->right_object_name_attribute_alias);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->right_object_name_axis = $axis;
        }
        return $this->right_object_name_axis;
    }
    
    /**
     * get Data Column for Right Object Name
     *
     * @return DataColumn
     */
    public function getRightObjectNameDataColumn() : DataColumn
    {
        return $this->getRightObjectNameAxis()->getDataColumn();
    }
    
    /**
     * get the attribute alias for relation
     *
     * @return MetaAttributeInterface
     */
    public function getRelationAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->relation_attribute_alias);
    }
    
    /**
     * Attribute alias for relation id (relative to the meta object of the chart series).
     *
     * @uxon-property relation_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $aliasRelativeToSeriesObject
     * @return GraphChartSeries
     */
    public function setRelationAttributeAlias(string $aliasRelativeToSeriesObject) : GraphChartSeries
    {
        $this->relation_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * get the axis for the relation
     *
     * @return ChartAxis
     */
    public function getRelationAxis() : ChartAxis
    {
        if ($this->relation_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getRelationAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->relation_attribute_alias);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->relation_axis = $axis;
        }
        return $this->relation_axis;
    }
    
    /**
     * get Data Column for Relation
     *
     * @return DataColumn
     */
    public function getRelationDataColumn() : DataColumn
    {
        return $this->getRelationAxis()->getDataColumn();
    }
    
    /**
     * get the attribute alias for relation name
     *
     * @return MetaAttributeInterface
     */
    public function getRelationNameAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->relation_name_attribute_alias);
    }
    
    /**
     * Attribute alias for relation name (relative to the meta object of the chart series).
     *
     * @uxon-property relation_name_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $aliasRelativeToSeriesObject
     * @return GraphChartSeries
     */
    public function setRelationNameAttributeAlias(string $aliasRelativeToSeriesObject) : GraphChartSeries
    {
        $this->relation_name_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * get the axis for relation name
     *
     * @return ChartAxis
     */
    public function getRelationNameAxis() : ChartAxis
    {
        if ($this->relation_name_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getRelationNameAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->relation_name_attribute_alias);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->relation_name_axis = $axis;
        }
        return $this->relation_name_axis;
    }
    
    /**
     * get Data Column for Relation Name
     *
     * @return DataColumn
     */
    public function getRelationNameDataColumn() : DataColumn
    {
        return $this->getRelationNameAxis()->getDataColumn();
    }
    
    /**
     * get the attribute alias for direction
     *
     * @return MetaAttributeInterface
     */
    public function getDirectionAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->direction_attribute_alias);
    }
    
    /**
     * Attribute alias for direction (relative to the meta object of the chart series).
     *
     * @uxon-property direction_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $aliasRelativeToSeriesObject
     * @return GraphChartSeries
     */
    public function setDirectionAttributeAlias(string $aliasRelativeToSeriesObject) : GraphChartSeries
    {
        $this->direction_attribute_alias = $aliasRelativeToSeriesObject;
        return $this;
    }
    
    /**
     * get the axis for the direction
     *
     * @return ChartAxis
     */
    public function getDirectionAxis() : ChartAxis
    {
        if ($this->direction_axis === null) {
            $axes = $this->getChart()->findAxesByAttribute($this->getDirectionAttribute());
            if (empty($axes)) {
                $axis = $this->getChart()->createAxisFromExpression($this->direction_attribute_alias);
                $this->getChart()->addAxisY($axis);
            } else {
                $axis = $axes[0];
            }
            $this->direction_axis = $axis;
        }
        return $this->direction_axis;
    }
    
    /**
     * get Data Column for Direction
     *
     * @return DataColumn
     */
    public function getDirectionDataColumn() : DataColumn
    {
        return $this->getDirectionAxis()->getDataColumn();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Charts\ChartSeries::prepareAxes()
     */
    public function prepareDataWidget(iShowData $dataWidget) : ChartSeries
    {
        $this->getLeftObjectAxis();
        $this->getLeftObjectNameAxis();
        $this->getRightObjectAxis();
        $this->getRightObjectNameAxis();
        $this->getRelationAxis();
        $this->getRelationNameAxis();
        $this->getDirectionAxis();
        return $this;
    }
}