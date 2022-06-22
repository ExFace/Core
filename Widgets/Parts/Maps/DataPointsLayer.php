<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Traits\iHaveColorTrait;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Widgets\Parts\Maps\Traits\DataPointLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\EditableMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngWidgetLinkMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngDataColumnMapLayerInterface;
use exface\Core\Interfaces\Widgets\iHaveColor;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class DataPointsLayer extends AbstractDataLayer 
    implements 
    iHaveColor, 
    iHaveColorScale,
    LatLngDataColumnMapLayerInterface,
    LatLngWidgetLinkMapLayerInterface,
    EditableMapLayerInterface
{
    use DataPointLayerTrait {
        initDataWidget as initDataWidgetForPoints;
    }
    
    use iHaveColorTrait;
    
    use iHaveColorScaleTrait;
    
    private $colorAttributeAlias = null;
    
    private $colorColumn = null;
    
    private $size = 10;
    
    /**
     *
     * @return string|NULL
     */
    public function getColorAttributeAlias() : ?string
    {
        return $this->colorAttributeAlias;
    }
    
    /**
     * Alias of the attribtue containing the color value or the base for the `color_scale`
     *
     * @uxon-property color_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return DataMarkersLayer
     */
    public function setColorAttributeAlias(string $value) : DataMarkersLayer
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
        return $this->colorColumn;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $widget = $this->initDataWidgetForPoints($widget);
        
        if ($this->getColorAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($this->getColorAttributeAlias())) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->getColorAttributeAlias(),
                    'visibility' => WidgetVisibilityDataType::HIDDEN
                ]));
                $widget->addColumn($col, 0);
            }
            $this->colorColumn = $col;
        }
        
        return $widget;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::isColorScaleRangeBased()
     */
    public function isColorScaleRangeBased() : bool
    {
        if (! $this->hasColor()) {
            return false;
        }
        $dataType = $this->getColorColumn()->getDataType();
        switch (true) {
            case $dataType instanceof NumberDataType:
            case $dataType instanceof DateDataType:
                return true;
        }
        
        return false;
    }
    
    /**
     * 
     * @return int
     */
    public function getPointSize() : int
    {
        return $this->size;
    }
    
    /**
     * The diameter of each point in pixels
     * 
     * @uxon-property point_size
     * @uxon-type integer
     * @uxon-default 10
     * 
     * @param int $value
     * @return DataPointsLayer
     */
    public function setPointSize(int $value) : DataPointsLayer
    {
        $this->size = $value;
        return $this;
    }
}