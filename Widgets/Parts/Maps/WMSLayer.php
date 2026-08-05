<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Widgets\Parts\Maps\BaseMaps\WMS;

/**
 * Allows to use a WMS URL as a map layer instead of a base map.
 * 
 * ### Examples
 *
 *   **GIS WMS Example**
 *
 *  This is an imaginary example based on a real example from a former project how a wms to a gis could look like.
 *  ```
 *  {
 *       "url": "https://gis.example.com/project/services/WMS_SomethingMap/MapServer/WMSServer",
 *       "type": "WMS",
 *       "//": "this is very expensive since every zoom we will request a new image."
 *       "format": "image/png",
 *       "//": "depending on the url you need multiple layers to see everything. e.g. '0,1,2,3'"
 *       "layers": 0,
 *       "auto_zoom_to_see_all": false,
 *       "attribution": "This will show in the bottom right corner of the map",
 *       "transparent": true,
 *       "caption": "This will show in the legend of the map"
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 * @summary_author Miriam Seitz
 *
 */
class WMSLayer extends WMS
{   
}