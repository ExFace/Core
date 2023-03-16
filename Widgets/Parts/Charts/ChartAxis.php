<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\Chart;
use exface\Core\DataTypes\SortingDirectionsDataType;

/**
 * Explicit configuration of the X or Y axis of a chart.
 *
 * Most important properties of a `ChartAxis` are:
 * 
 * - `caption` - derived from the caption of the axis data column if not set explicitly
 * - `hide_caption` - for all axis types except `time` set to `false` by default
 * - `axis_type` (`time`, `category`, `value`, etc.) - determined automatically from the data type
 * - `position` (left, right, bottom, top) 
 * - `min`/`max` values - determined automatically from the data if not set explicitly
 * - `hidden` - always `false` unless explicitly set to `true`
 *
 * @author Andrej Kabachnik
 *        
 */
class ChartAxis extends AbstractChartPart implements iHaveCaption
{
    use iHaveCaptionTrait {
        getCaption as getCaptionSetExplicitly;
        getHideCaption as getHideCaptionSetExplicitly;
    }

    private $axis_type = null;

    private $data_column_id = null;
    
    private $data_column = null;
    
    private $attributeAlias = null;

    private $min_value = null;

    private $max_value = null;

    private $position = null;
    
    private $hidden = null;
    
    private $reverse_direction = null;
    
    private $zoomable = null;
    
    private $grid = null;
    
    private $gridArea = null;
    
    private $rotate_labels_degrees = null;
    
    private $ticks_for_every_value = null;

    const POSITION_TOP = 'TOP';

    const POSITION_RIGHT = 'RIGHT';

    const POSITION_BOTTOM = 'BOTTOM';

    const POSITION_LEFT = 'LEFT';
    
    const AXIS_TYPE_TIME = 'TIME';
    
    const AXIS_TYPE_CATEGORY = 'CATEGORY';
    
    const AXIS_TYPE_VALUE = 'VALUE';
    
    const AXIS_TYPE_LOG = 'LOG';

    /**
     *
     * @return DataColumn
     */
    public function getDataColumn() : DataColumn
    {
        if ($this->data_column === null) {
            if ($this->data_column_id !== null) {
                if ($col = $this->getChart()->getData()->getColumn($this->data_column_id)) {
                    $this->data_column = $col;
                } else {
                    throw new WidgetConfigurationError($this->getChart(), 'Cannot create ' . $this->getDimension() . '-axis: no data column found with id "' . $this->data_column_id . '"!', '769139J');
                }
            } else {
                throw new WidgetConfigurationError($this->getChart(), 'Cannot create ' . $this->getDimension() . '-axis: either specify a data_column_id or an attribute_alias!', '769139J');
            }
        }
        return $this->data_column;
    }

    /**
     * Specifies the data column to use for values of this axis by the column's id.
     *
     * @uxon-property data_column_id
     * @uxon-type uxon:$..id
     *
     * @param string $value            
     */
    public function setDataColumnId($value) : ChartAxis
    {
        $this->data_column_id = $value;
        return $this;
    }
    
    /**
     * Alias of the attribute to be displayed on this axis.
     * 
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return ChartAxis
     */
    public function setAttributeAlias(string $value) : ChartAxis
    {
        $this->attributeAlias = $value;
        return $this;
    }

    /**
     * 
     * @return number|string
     */
    public function getMinValue()
    {
        return $this->getDataColumn()->getDataType()->parse($this->min_value);
    }

    /**
     * Sets the minimum value for the scale of this axis.
     * 
     * The value must be a valid value for the axis's data type!
     * 
     * If not set, the minimum value of the underlying data will be used.
     *
     * @uxon-property min_value
     * @uxon-type string
     *
     * @param number|string $value            
     */
    public function setMinValue($value)
    {
        $this->min_value = $value;
    }

    /**
     * 
     * @return number|string
     */
    public function getMaxValue()
    {
        return $this->getDataColumn()->getDataType()->parse($this->max_value);
    }

    /**
     * Sets the maximum value for the scale of this axis.
     * 
     * The value must be a valid value for the axis's data type!
     * 
     * If not set, the maximum value of the underlying data will be used.
     *
     * @uxon-property max_value
     * @uxon-type string
     *
     * @param number|string $value            
     */
    public function setMaxValue($value)
    {
        $this->max_value = $value;
    }

    /**
     * @return string
     */
    public function getPosition() : string
    {
        if ($this->position === null) {
            $this->position = $this->getDimension() === Chart::AXIS_X ? self::POSITION_BOTTOM : self::POSITION_LEFT;
        }
        return $this->position;
    }

    /**
     * Defines the position of the axis on the chart: LEFT/RIGHT for Y-axes and TOP/BOTTOM for X-axes.
     *
     * @uxon-property position
     * @uxon-type [top,bottom,right,left]
     *
     * @param string $value            
     * @return ChartAxis
     */
    public function setPosition($value) : ChartAxis
    {
        $value = mb_strtoupper($value);
        if (defined(__CLASS__ . '::POSITION_' . $value)) {
            $this->position = $value;
        } else {
            throw new WidgetPropertyInvalidValueError($this->getChart(), 'Invalid axis position "' . $value . '". Only TOP, RIGHT, BOTTOM or LEFT allowed!', '6TA2Y6A');
        }
        return $this;
    }
    
    /**
     * Set this parameter if you want the axis label be rotated by 45 degrees. Only works for x-axes.
     * 
     * @uxon-property rotate_labels
     * @uxon-type bool
     *
     * 
     * @param boolean $bool
     * @return ChartAxis
     */
    public function setRotateLabels(bool $bool) : ChartAxis
    {
        if ($bool === false) {
            $this->rotate_labels_degrees = null;
            return $this;
        }
        if ($this->rotate_labels_degrees === null) {
            $this->setRotateLabelsDegrees(45);
        }
        return $this;
    }
    
    /**
     * Set this parameter if you want the axis label be rotated by 45 degree. Only works for x-axes.
     *
     * @uxon-property rotate_labels_degrees
     * @uxon-type [45,90,-45,-90]
     *
     *
     * @param int
     * @return ChartAxis
     */
    public function setRotateLabelsDegrees(int $degrees) : ChartAxis
    {
        $this->rotate_labels_degrees = $degrees;
        return $this;
    }
    
    /**
     * Returns how many degree axis lables should get rotated.
     * @return string
     */
    public function getRotateLabelsDegree() : string
    {
        $degrees = $this->rotate_labels_degrees;
        if (abs($degrees) !== 45 && abs($degrees) !== 90) {
            throw new WidgetPropertyInvalidValueError($this->getChart(), 'Invalid chart axis label rotation "' . $degrees . '". Only the values 45, 90, -45 or -90 are allowed!', '6TA2Y6A');
        }
        return $degrees;
    }
    
    /**
     * returns TRUE when the labels need to be rotated, always false for y-axis
     * 
     * @return bool
     */
    public function hasRotatedLabel() : bool
    {
        if ($this->getDimension() === Chart::AXIS_Y) {
            return false;
        }
        return $this->rotate_labels_degrees !== null;
    }

    /**
     * Returns the axis dimension: `x` or `y`.
     * @return string
     */
    public function getDimension() : string
    {
        return $this->getChart()->getAxisDimension($this);
    }

    /**
     * 
     * @return int
     */
    public function getIndex() : int
    {
        return $this->getChart()->getAxisIndex($this);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption() : ?string
    {
        if ($this->getCaptionSetExplicitly() === null) {
            if (($colCaption = $this->getDataColumn()->getCaption()) !== null) {
                $this->setCaption($colCaption);
            }
        }
        return $this->getCaptionSetExplicitly();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        
        if ($this->data_column_id !== null) {
            $uxon->setProperty('data_column_id', $this->data_column_id);
        }
        if ($this->attributeAlias !== null) {
            $uxon->setProperty('attribute_alias', $this->attributeAlias);
        }
        
        // TODO
        
        return $uxon;
    }
    
    /**
     *
     * @return bool
     */
    public function isHidden() : bool
    {
        return $this->hidden ?? false;
    }
    
    /**
     * Set to FALSE to make the axis invisible or to TRUE (default) to force showing it.
     * 
     * 
     * @uxon-property hidden
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return ChartAxis
     */
    public function setHidden(bool $value) : ChartAxis
    {
        $this->hidden = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isReverse(bool $default = false) : bool
    {
        return $this->reverse_direction ?? $default;
    }
    
    /**
     * Set to true to reverse the axis direction. Default is false
     * 
     * 
     * @uxon-property reverse_direction
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return ChartAxis
     */
    public function setReverseDirection(bool $value) : ChartAxis
    {
        $this->reverse_direction = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isZoomable() : ?bool
    {
        return $this->zoomable;
    }
    
    /**
     * Set to true to be able to zoom data on that axis
     *
     *
     * @uxon-property zoomable
     * @uxon-type boolean
     *
     * @param bool $value
     * @return ChartAxis
     */
    public function setZoomable(bool $value) : ChartAxis
    {
        $this->zoomable = $value;
        return $this;
    }
    
    /**
     * Set the axis type. Possible types are 'value', 'category', 'time', 'log'.
     * 
     * @uxon-property axis_type
     * @uxon-type [value,category,time,log]
     * 
     * @param string $type
     * @return ChartAxis
     */
    public function setAxisType(string $axis_type) : ChartAxis
    {
        $axis_type = mb_strtoupper($axis_type);
        if (defined(__CLASS__ . '::AXIS_TYPE_' . $axis_type)) {
            $this->axis_type = $axis_type;
        } else {
            throw new WidgetPropertyInvalidValueError($this->getChart(), 'Invalid axis type "' . $axis_type . '". Only TIME, CATEGORY, VALUE or LOG allowed!', '6TA2Y6A');
        }
        $this->axis_type = $axis_type;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getAxisType() : string
    {
        if ($this->axis_type === null){
            $dataType = $this->getDataColumn()->getDataType();
            switch (true) {
                case $dataType instanceof DateDataType || $dataType instanceof TimestampDataType : 
                    $this->axis_type = self::AXIS_TYPE_TIME;
                    break;
                case $dataType instanceof NumberDataType: 
                    $this->axis_type = self::AXIS_TYPE_VALUE;
                    break;
                default :
                    $this->axis_type = self::AXIS_TYPE_CATEGORY;
            }
            
        }
        return $this->axis_type;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasGrid() : bool
    {
        if($this->grid === null){
            return false;
        }
        return $this->grid;    
    }
    
    /**
     * Set to TRUE to make the gridline for this axis visible or to FALSE (default) to force hiding it.
     * 
     * @uxon-property grid
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return ChartAxis
     */
    public function setGrid(bool $value) : ChartAxis
    {
        $this->grid = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function hasGridArea() : bool
    {
        if($this->gridArea === null){
            return false;
        }
        return $this->gridArea;
    }
    
    /**
     * Set to TRUE to make the grid areas for this axis visible or to FALSE (default) to force hiding it.
     *
     * @uxon-property grid_area
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return ChartAxis
     */
    public function setGridArea(bool $trueOrFalse) : ChartAxis
    {
        $this->gridArea = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * @param iShowData $dataWidget
     * @throws WidgetConfigurationError
     * @return ChartAxis
     */
    public function prepareDataWidget(iShowData $dataWidget) : ChartAxis
    {
        if ($this->attributeAlias !== null) {
            if (! $column = $dataWidget->getColumnByAttributeAlias($this->attributeAlias)) {
                $column = $dataWidget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->attributeAlias
                ]));
                $dataWidget->addColumn($column);
            }
        } elseif ($this->data_column_id !== null) {
            if (! $column = $dataWidget->getColumn($this->data_column_id)) {
                $column = $dataWidget->getColumnByAttributeAlias($this->data_column_id);
                if (! $column) {
                    throw new WidgetConfigurationError($this, 'Column "' . $this->data_column_id . '" required for axis ' . $this->getNumber() . ' not found in chart data!', '6XUZ9ZE');
                }
            }
        } else {
            throw new WidgetConfigurationError($this, 'Invalid chart axis configuration: neither attribute_alias nor data_colum_id were specified!', '6XUZ9ZE');
        }
        
        $this->data_column = $column;
        
        // If we have a time axis, we should to sort data - unsorted time looks really strange :)
        // Still, the user may choose to sort based on other criteria.
        if ($this->getAxisType() === self::AXIS_TYPE_TIME) {
            // If sorting was not taken care of at all - sort over time.
            if (empty($dataWidget->getSorters()) === true) {
                $dataWidget->addSorter($this->getDataColumn()->getAttributeAlias(), SortingDirectionsDataType::ASC);
            }
        }
        
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isBoundToAttribute() : bool
    {
        if ($this->data_column !== null || $this->data_column_id !== null) {
            return $this->getDataColumn()->isBoundToAttribute();
        }
        return $this->attributeAlias !== null;
    }
    
    /**
     * 
     * @return bool
     */
    public function isBoundToDataColumn() : bool
    {
        if ($this->data_column !== null || $this->data_column_id !== null) {
            return $this->getDataColumn()->isBoundToDataColumn();
        }
        return $this->isBoundToAttribute();
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getAttribute() : MetaAttributeInterface
    {
        if ($this->data_column !== null || $this->data_column_id !== null) {
            return $this->getDataColumn()->getAttribute();
        }
        if ($this->attributeAlias !== null) {
            return $this->getChart()->getMetaObject()->getAttribute($this->attributeAlias);
        }
        return null;
    }
    
    /**
     * Set to TRUE to force the axis to have ticks and labels for every value - otherwise ticks will be distributed automatically.
     * 
     * By default, ticks (label-markings) on the axis are distributed automatically in an
     * attempt to keep the axis values readable and not overcrowded. This means, some values
     * will not be labeled if there are many values on the axis. This is perfectly OK for
     * numeric and time axis, but will generally not work well on category-axis (those with
     * custom text values). Indeed, if the values of the axis are names (e.g. cities, countries, 
     * etc.) you can't just skip a name, while skipping a date in a timeline is perfectly fine.
     * 
     * However, sometimes you may want to force to enable or disable auto-distribution of
     * ticks explicitly. This can be done by setting `ticks_for_every_value` to `true` or
     * `false` respectively.
     * 
     * If not set explicitly, this property uses the following default values:
     * 
     * - `true` for category axes
     * - `false` in all other cases
     * 
     * @uxon-property ticks_for_every_value
     * @uxon-type bool
     * 
     * @param bool $trueOrFalse
     * @return ChartAxis
     */
    public function setTicksForEveryValue(bool $trueOrFalse) : ChartAxis
    {
        $this->ticks_for_every_value = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasTicksForEveryValue() : bool
    {
        return $this->ticks_for_every_value ?? $this->getAxisType() === self::AXIS_TYPE_CATEGORY;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see iHaveCaptionTrait::getHideCaption()
     */
    public function getHideCaption() : ?bool
    {
        return $this->getHideCaptionSetExplicitly() ?? ($this->getAxisType() === self::AXIS_TYPE_TIME ? true : false);
    }
}