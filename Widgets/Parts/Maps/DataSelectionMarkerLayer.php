<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\Maps\Interfaces\DataSelectionMapLayerInterface;

/**
 * Shows a markers rendered from data selected in another widget. The marker is an interactive element with its own pop-up.
 * 
 * ### Data format
 * 
 * The data for a marker needs to be longitude and latitude values.
 * 
 * ### Data selection
 * 
 * For data selection you need a `data_widget_link` or your context data if your object_alias refers to a single entry in your context (the dialog/page etc.).
 * 
 * ### Selection from a table via data widget link
 * 
 * ```
 * {
 *      "//": "Marker for selected item in table",
 *      "type": "DataSelectionMarker",
 *      "object_alias": "the.app.your_object_alias",
 *      "caption": "Your-Marker",
 *      "data_widget_link": "=your_table_id",
 *      "latitude_attribute_alias": "your_latitude_alias",
 *      "longitude_attribute_alias": "your_longitude_alias",
 *      "//icon": "you can change the icon but that will ignore the color_attribute_alias for there is no more color, you can color the svg if you need a different color",
 *      "color_attribute_alias": "your_color_alias"
 * }
 * 
 * ```
 * 
 * ### Selection as the current object of your context (the dialog/page etc.)
 * 
 * It will also work with multiple entries, but then all of them will receive a marker.
 * 
 * ```
 * {
 *      "//": "Marker for the current object of this context",
 *      "type": "DataSelectionMarker",
 *      "//": "No data_widget_link is necessary if your object-alias aligns with an object that only contains one data entry, like in details dialog, but keep in mind that a data in another layer with the same object-alias will change your context."
 *      "latitude_attribute_alias": "your_latitude_alias",
 *      "longitude_attribute_alias": "your_longitude_alias",
 *      "caption": "Your-Marker",
 *      "//icon": "you can change the icon but that will ignore the color_attribute_alias for there is no more color, you can color the svg if you need a different color",
 *      "color": "#fe87fa"
 * }
 * 
 * ```
 * 
 * ### Widget Links DON'T WORK with this layer!
 * 
 * Do not use `latitude_widget_link` or `longitude_widget_link` for this layer. You need data for a **Data**SelectionMarker.
 * 
 * @author Andrej Kabachnik
 * @summary_author Miriam Seitz
 *
 */
class DataSelectionMarkerLayer extends DataMarkersLayer implements DataSelectionMapLayerInterface
{
    /**
     * 
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::setData()
     */
    public function setData(UxonObject $uxon) : DataMarkersLayer
    {
        throw new WidgetConfigurationError($this->getMap(), 'Cannot use custom `data` with a "' . $this->getType() . '" layer - use `data_widget_link` instead!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\DataMarkersLayer::isClusteringMarkers()
     */
    public function isClusteringMarkers() : ?bool
    {
        return parent::isClusteringMarkers() ?? false;
    }
}