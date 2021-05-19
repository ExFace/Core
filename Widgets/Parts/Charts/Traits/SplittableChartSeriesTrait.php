<?php
namespace exface\Core\Widgets\Parts\Charts\Traits;

use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\DataTypes\BooleanDataType;

trait SplittableChartSeriesTrait
{
    /**
     * 
     * @var string
     */
    private $split_by_attribute_alias = null;
    
    private $split_by_axis = null;
    
    private $split_use_color_gradients = null;
    
    /**
     * Set this attribute when you want to split the dataset into parts and get a series for
     * each value that attribute has.
     * For example you want to see the sales of different stores over time, you split the dataset
     * by the store attribute and will get a chart with different series for each store
     *
     * @uxon-property split_by_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return ChartSeries
     */
    public function setSplitByAttributeAlias(string $value) : ChartSeries
    {
        $this->split_by_attribute_alias = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getSplitByAttributeAlias() : ?string
    {
        return $this->split_by_attribute_alias;
    }
    
    /**
     * Set this attribute when you want series resulting from a split to use color gradients instead of different colors
     * 
     * If it is set to `true` (default) the `color` of the series will be use to generated 
     * gradients for every split. If no `color` is set, the auto-assigned color will be 
     * used for gradients.
     * 
     * If it is set to `false`, the possibly set color for the series will be ignored - instead each split
     * will get it's own color.
     *
     * @uxon-property split_with_color_gradients
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param string $value
     * @return ChartSeries
     */
    public function setSplitWithColorGradients(bool $trueOrFalse) : ChartSeries
    {
        $this->split_use_color_gradients = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getSplitWithColorGradients() : bool
    {
        if ($this->split_use_color_gradients !== null) {
            return $this->split_use_color_gradients;
        }
        return true;
    }
    
    /**
     *
     * @return bool
     */
    public function isSplitByAttribute() : bool
    {
        return $this->getSplitByAttributeAlias() !== null;
    }
    
    /**
     * 
     * @throws WidgetLogicError
     * @return MetaAttributeInterface
     */
    public function getSplitByAttribute() : MetaAttributeInterface
    {
        if ($this->isSplitByAttribute() === false) {
            throw new WidgetLogicError($this->getChart(), 'Requested split-attribute while chart series is not split by attribute!');
        }
        return $this->getMetaObject()->getAttribute($this->getSplitByAttributeAlias());
    }
    
    /**
     * 
     * @throws WidgetLogicError
     * @return DataColumn
     */
    public function getSplitByDataColumn() : DataColumn
    {
        if ($this->isSplitByAttribute() === false) {
            throw new WidgetLogicError($this->getChart(), 'Requested split-attribute while chart series is not split by attribute!');
        }
        return $this->getChart()->getData()->getColumnByAttributeAlias($this->getSplitByAttributeAlias());
    }
}