<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iHaveIcon;
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
use exface\Core\Widgets\Traits\iHaveIconTrait;

/**
 * A map layer to draw point(s) into a map using longitude and latitude values.
 * 
 * ### Data Setup Guide
 *
 *  please open the properties of widget_type 'Map' for a full guide on how to pass on data to any layer in a map widget!
 * 
 * ### Widget Configuration Guide
 * 
 * **Widget Links**
 * 
 * ```
 * {
 *      "widget_type": "Input",
 *      "value": 450196.06,
 *      "caption": "UTM Longitude",
 *      "data_column_name": "UTM_Longitude",
 *      "id": "your_long_widget"
 * },
 * {
 *      "widget_type": "Input",
 *      "value": 5427449.28,
 *      "data_column_name": "UTM_Latitude",
 *      "caption": "UTM Latitude",
 *      "id": "your_lat_widget"
 * }
 * 
 * ...
 *
 * {
 *       "type": "DataPoints",
 *       "caption": "Your Marker",
 *       "latitude_widget_link": "=your_lat_widget",
 *       "longitude_widget_link": "=your_long_widget",
 *       "//": "UTM needs to be projected since this is not the native leaflet format. see https://epsg.io/ to find other formats."
 *       "projection": {
 *           "name": "EPSG:25832",
 *           "definition": "+proj=utm +zone=32 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs +type=crs"
 *       }
 * }
 * 
 * ```
 * 
 * **data with value links via attribute alias**
 *
 *  ```
 *  {
 *      "type": "DataPoints",
 *      "caption": "Your point from client data",
 *      "latitude_attribute_alias": "your_latitude_alias",
 *      "longitude_attribute_alias": "your_longitude_alias",
 *      "value_attribute_alias": "LABEL",
 *      "color_attribute_alias": "your_color_alias",
 *      "//icon": "you can change the icon but that will ignore the color_attribute_alias"
 *      "//": "This is a Gauss Krueger example and needs to be projected since this is not the native leaflet format. see https://epsg.io/ to find other formats."
 *      "projection": {
 *          "name": "EPSG:31467",
 *          "definition": "+proj=tmerc +lat_0=0 +lon_0=8.999 +k=1 +x_0=3500000 +y_0=0 +ellps=bessel +units=m +no_defs +type=crs"
 *      }
 *  }
 *
 * ```
 * 
 * @author Andrej Kabachnik
 * @summary_author Miriam Seitz
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
    CustomProjectionMapLayerInterface,
    iHaveIcon
{
    use DataPointLayerTrait;
    
    use ColoredLayerTrait;
    
    use ValueLabeledLayerTrait;
    
    use CustomProjectionLayerTrait;
    
    use iHaveIconTrait;
    
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
            switch (true) {
                case $this->getIcon() !== null:
                    $pt = 18;
                    break;
                case $this->getValuePosition() === PointMapLayerInterface::VALUE_POSITION_CENTER;
                    $pt = 30;
                    break;
                default:
                    $pt = 10;
            }
            return $pt;
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