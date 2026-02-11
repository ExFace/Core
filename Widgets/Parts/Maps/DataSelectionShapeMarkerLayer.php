<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Parts\Maps\Interfaces\CustomProjectionMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\DataSelectionMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\ShapeDataColumnMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\TooltipDataColumnMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\CustomProjectionLayerTrait;
use exface\Core\Widgets\Parts\Maps\Traits\DataShapesLayerTrait;
use exface\Core\Widgets\Parts\Maps\Traits\DataTooltipLayerTrait;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\MarkerMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\ColoredDataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\ValueLabeledLayerTrait;
use exface\Core\Widgets\Parts\Maps\Traits\ColoredLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\ValueLabeledMapLayerInterface;

/**
 * Shows a marker on the shape(s) rendered from data selected in another widget
 * 
 * This is similar to DataSelectionMarker, but works with GeoJSON shapes stored in data columns. Basically,
 * instead of `latitude_attribute_alias` and `longitude_attribute_alias` this layer uses `shape_attribute_alias`.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSelectionShapeMarkerLayer extends AbstractDataLayer 
    implements
    ShapeDataColumnMapLayerInterface,
    DataSelectionMapLayerInterface,
    MarkerMapLayerInterface, 
    ColoredDataMapLayerInterface,
    ValueLabeledMapLayerInterface, 
    TooltipDataColumnMapLayerInterface,
    CustomProjectionMapLayerInterface
{    
    use DataShapesLayerTrait;
    
    use iHaveIconTrait;
    
    use ColoredLayerTrait;
    
    use ValueLabeledLayerTrait;
    
    use DataTooltipLayerTrait;

    use CustomProjectionLayerTrait;

    private $clustering = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MarkerMapLayerInterface::isClusteringMarkers()
     */
    public function isClusteringMarkers() : ?bool
    {
        return $this->clustering ?? false;
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
    public function setClusterMarkers(bool $value) : DataSelectionShapeMarkerLayer
    {
        $this->clustering = $value;
        return $this;
    }

    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $widget = parent::initDataWidget($widget);
        $widget = $this->initDataWidgetShapeColumn($widget);

        $widget = $this->initDataWidgetColor($widget);
        $widget = $this->initDataWidgetValue($widget);
        $widget = $this->initDataWidgetTooltip($widget);

        return $widget;
    }
}