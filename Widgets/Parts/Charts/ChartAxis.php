<?php
namespace exface\Core\Widgets\Parts\Charts;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iShowData;

/**
 * The ChartAxis represents the X or Y axis of a chart.
 *
 * Most important properties of a ChartAxis are it's `caption`, `axis_type` (time, text, numbers, etc.), 
 * `position` (left, right, bottom, top) and `min`/`max` values. An axis can also be `hidden`.
 *
 * @author Andrej Kabachnik
 *        
 */
class ChartAxis extends AbstractChartPart implements iHaveCaption
{
    use iHaveCaptionTrait {
        getCaption as getCaptionViaTrait;
    }

    private $axis_type = null;

    private $data_column_id = null;
    
    private $data_column = null;
    
    private $attributeAlias = null;

    private $min_value = null;

    private $max_value = null;

    private $position = null;
    
    private $hidden = false;

    const POSITION_TOP = 'TOP';

    const POSITION_RIGHT = 'RIGHT';

    const POSITION_BOTTOM = 'BOTTOM';

    const POSITION_LEFT = 'LEFT';

    const AXIS_TYPE_TIME = 'TIME';

    const AXIS_TYPE_TEXT = 'TEXT';

    const AXIS_TYPE_NUMBER = 'NUMBER';

    /**
     *
     * @return DataColumn
     */
    public function getDataColumn() : DataColumn
    {
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
    public function setDataColumnId($value)
    {
        $this->data_column_id = $value;
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

    public function getMinValue()
    {
        return $this->min_value;
    }

    /**
     * Sets the minimum value for the scale of this axis.
     * If not set, the minimum value of the underlying data will be used.
     *
     * @uxon-property min_value
     * @uxon-type number
     *
     * @param float $value            
     */
    public function setMinValue($value)
    {
        $this->min_value = $value;
    }

    public function getMaxValue()
    {
        return $this->max_value;
    }

    /**
     * Sets the maximum value for the scale of this axis.
     * If not set, the maximum value of the underlying data will be used.
     *
     * @uxon-property max_value
     * @uxon-type number
     *
     * @param float $value            
     */
    public function setMaxValue($value)
    {
        $this->max_value = $value;
    }

    public function getPosition()
    {
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
    public function setPosition($value)
    {
        $value = mb_strtoupper($value);
        if (defined(__CLASS__ . '::POSITION_' . $value)) {
            $this->position = $value;
        } else {
            throw new WidgetPropertyInvalidValueError($this->getChart(), 'Invalid axis position "' . $value . '". Only TOP, RIGHT, BOTTOM or LEFT allowed!', '6TA2Y6A');
        }
        return $this;
    }

    public function getDimension()
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
    public function getCaption()
    {
        if ($this->getCaptionViaTrait() === null) {
            $this->setCaption($this->getDataColumn()->getCaption());
        }
        return $this->getCaptionViaTrait();
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
        return $this->hidden;
    }
    
    /**
     * Set to TRUE to make the axis invisible.
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
     * @param iShowData $dataWidget
     * @throws WidgetConfigurationError
     * @return AbstractChartType
     */
    public function prepareData(iShowData $dataWidget) : ChartAxis
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
        
        return $this;
    }
}