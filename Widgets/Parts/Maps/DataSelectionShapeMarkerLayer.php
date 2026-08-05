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
 * Shows a marker on the shape(s) rendered from data selected in another widget. The marker is an interactive element with its own pop-up.
 * 
 * ### Data format
 * 
 * The data for a marker needs to be a GeoJSON.
 * 
 * ### Data Setup Guide
 * 
 * please open the properties of widget_type `Map` for a full guide on how to pass on data to any layer in a map widget!
 * 
 * ### Marker Icon
 * 
 * You can change the `icon` of a marker to any svg but doing that means loosing control over the color of the marker, so you might want to color the svg directly.
 * 
 * ### Selection from a table via widget link
 * 
 * ```
 * {
 *      "//": "Marker for selected item in table",
 *      "type": "DataSelectionShapeMarker",
 *      "object_alias": "the.app.your_object_alias",
 *      "caption": "Your-Marker",
 *      "data_widget_link": "=your_table_id",
 *      "shapes_attribute_alias": "your_shape_alias",
 *      "//icon": "you can change the icon but that will ignore the color_attribute_alias",
 *      "color_attribute_alias": "your_color_alias"
 * }
 * 
 * ```
 * 
 * ### Selection as the current object of your context (the dialog/page etc.)
 * 
 * ```
 * {
 *      "//": "Marker for the current object of this context",
 *      "type": "DataSelectionShapeMarker",
 *      "shapes_attribute_alias": "your_shape_alias",
 *      "caption": "Your-Marker",
 *      "//": "If you parent widget is the same object and only contains one entry (Id) you don't need data, for it is your 'current' entry. You can still use it to configure the pop-up.",
 *      "data": {
 *          "object_alias": "the.app.your_object_alias",
 *          "//": "define the data in the map pop-up",
 *          "columns": [
 *              {
 *                  "~snippet": "the.app.your_snippet_alias"
 *              }
 *          ]
 *      },
 *      "//icon": "you can change the icon but that will ignore the color_attribute_alias",
 *      "color": "#fe87fa"
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 * @summary_author Miriam Seitz
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