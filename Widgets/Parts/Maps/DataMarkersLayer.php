<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\MarkerMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\DataPointLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngDataColumnMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngWidgetLinkMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\EditableMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\ColoredDataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\ValueLabeledLayerTrait;
use exface\Core\Widgets\Parts\Maps\Traits\ColoredLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\ValueLabeledMapLayerInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class DataMarkersLayer extends AbstractDataLayer 
    implements 
    MarkerMapLayerInterface, 
    LatLngDataColumnMapLayerInterface, 
    LatLngWidgetLinkMapLayerInterface,
    ColoredDataMapLayerInterface,
    ValueLabeledMapLayerInterface, 
    EditableMapLayerInterface
{
    use DataPointLayerTrait {
        initDataWidget as initDataWidgetForPoints;
    }
    
    use iHaveIconTrait;
    
    use ColoredLayerTrait;
    
    use ValueLabeledLayerTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $widget = $this->initDataWidgetForPoints($widget);
        $widget = $this->initDataWidgetValue($widget);
        $widget = $this->initDataWidgetColor($widget);
        
        return $widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MarkerMapLayerInterface::isClusteringMarkers()
     */
    public function isClusteringMarkers() : ?bool
    {
        return $this->clustering ?? ($this->isEditable() ? false : null);
    }
    
    /**
     * Set to TRUE to group markers to clusters or to FALSE to disable clustering explicitly.
     * 
     * By default, the facade will decide itself, if clustering is appropriate for this layer.
     * 
     * @uxon-property cluster_markers
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return DataMarkersLayer
     */
    public function setClusterMarkers(bool $value) : DataMarkersLayer
    {
        $this->clustering = $value;
        return $this;
    }
    
    /**
     * @deprecated use the generic setEditByAddingItems()
     * @param bool $value
     * @return DataMarkersLayer
     */
    protected function setAllowToAddMarkers(bool $value) : DataMarkersLayer
    {
        return $this->setEditByAddingItems($value);
    }
    
    /**
     * @deprecated use the generic setEditByMovingItems()
     * @param bool $value
     * @return DataMarkersLayer
     */
    protected function setAllowToMoveMarkers(bool $value) : DataMarkersLayer
    {
        return $this->setEditByMovingItems($value);
    }
}