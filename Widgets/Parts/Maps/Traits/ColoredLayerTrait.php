<?php
namespace exface\Core\Widgets\Parts\Maps\Traits;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Widgets\Traits\iHaveColorTrait;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Parts\Maps\Interfaces\ValueLabeledMapLayerInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * This trait adds color related properties to a map layer
 * 
 * - `color`,
 * - `color_scale`,
 * - `color_attribute`
 * 
 * @author Andrej Kabachnik
 *
 */
trait ColoredLayerTrait
{
    use iHaveColorTrait;
    
    use iHaveColorScaleTrait;
    
    private $colorAttributeAlias = null;
    
    private $colorColumn = null;
    
    /**
     *
     * @return string|NULL
     */
    public function getColorAttributeAlias() : ?string
    {
        return $this->colorAttributeAlias;
    }
    
    /**
     * Alias of the attribtue containing the exact color value or base value for the `color_scale`
     *
     * @uxon-property color_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setColorAttributeAlias(string $value) : MapLayerInterface
    {
        $this->colorAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function hasColor() : bool
    {
        return $this->getColorAttributeAlias() !== null;
    }
    
    /**
     *
     * @return DataColumn|NULL
     */
    public function getColorColumn() : ?DataColumn
    {
        if ($this->colorColumn === null && $this->hasColorScale() && ($this instanceof ValueLabeledMapLayerInterface)) {
            return $this->getValueColumn();
        }
        return $this->colorColumn;
    }
    
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::isColorScaleRangeBased()
     */
    public function isColorScaleRangeBased(DataTypeInterface $dataType = null) : bool
    {
        if (! $this->hasColor()) {
            return false;
        }
        $dataType = $dataType ?? $this->getColorColumn()->getDataType();
        switch (true) {
            case $dataType instanceof NumberDataType:
            case $dataType instanceof DateDataType:
                return true;
        }
        
        return false;
    }
    
    /**
     * 
     * @param iShowData $widget
     * @return iShowData
     */
    protected function initDataWidgetColor(iShowData $widget) : iShowData
    {
        if (null !== $alias = $this->getColorAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($alias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $alias,
                    'visibility' => WidgetVisibilityDataType::HIDDEN
                ]));
                $widget->addColumn($col, 0);
            }
            $this->colorColumn = $col;
        }
        
        return $widget;
    }
}