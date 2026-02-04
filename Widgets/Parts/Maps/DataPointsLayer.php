<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Parts\Maps\Interfaces\PointMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\DataPointLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\EditableMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngWidgetLinkMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngDataColumnMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\ColoredDataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\ColoredLayerTrait;
use exface\Core\Widgets\Parts\Maps\Traits\ValueLabeledLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\ValueLabeledMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\CustomProjectionMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\CustomProjectionLayerTrait;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class DataPointsLayer extends AbstractDataLayer 
    implements
    PointMapLayerInterface,
    LatLngDataColumnMapLayerInterface,
    LatLngWidgetLinkMapLayerInterface,
    ColoredDataMapLayerInterface,
    ValueLabeledMapLayerInterface,
    EditableMapLayerInterface,
    CustomProjectionMapLayerInterface
{
    use DataPointLayerTrait;
    
    use ColoredLayerTrait;
    
    use ValueLabeledLayerTrait;
    
    use CustomProjectionLayerTrait;
    
    private $size = null;
    
    private $valuePosition = PointMapLayerInterface::VALUE_POSITION_RIGHT;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $widget = $this->initDataWidgetPointColumns($widget);
        $widget = $this->initDataWidgetColor($widget);
        $widget = $this->initDataWidgetValue($widget);
        
        return $widget;
    }

    /**
     *
     * {@inheritDoc}
     * @see PointMapLayerInterface::getPointSize()
     */
    public function getPointSize() : int
    {
        if ($this->size === null) {
            return $this->getValuePosition() === PointMapLayerInterface::VALUE_POSITION_CENTER ? 30 : 10;
        }
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
    
    /**
     * 
     * {@inheritDoc}
     * @see PointMapLayerInterface::getValuePosition()
     */
    public function getValuePosition() : string
    {
        return $this->valuePosition;
    }
    
    /**
     * Where to show the value relatively to the point - right (default), left, top, bottom or center
     * 
     * @uxon-property value_position
     * @uxon-type [right,left,top,bottom,center]
     * @uxon-default right
     * 
     * @param string $value
     * @return DataPointsLayer
     */
    public function setValuePosition(string $value) : DataPointsLayer
    {
        $this->valuePosition = $value;
        return $this;
    }
}