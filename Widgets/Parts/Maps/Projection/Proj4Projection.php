<?php
namespace exface\Core\Widgets\Parts\Maps\Projection;

use exface\Core\Widgets\Parts\Maps\Interfaces\MapProjectionInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;

/**
 * Allows to use custom projections according to the Proj4 definition format
 * 
 * See https://epsg.io/ for details on the various projections and tools to
 * play around with the conversion or verify coordinates on a map.
 * 
 * ## Examples
 * 
 * ```
 *  {
 *      "name": "EPSG:25832",
 *      "definition": "+proj=utm +zone=32 +ellps=GRS80 +datum=NAD83 +units=m +no_defs"
 *  }
 *  
 * ```
 * 
 * ## Where to get the definitions?
 *  
 *  1. Go to https://epsg.io/
 *  2. Search for the desired coordinat system. If you know, the EPSG code (e.g. the `25832` from `EPSG:25832`),
 * just navigate to https://epsg.io/25832.
 *  3. Under "Available transformations" pick `EPSG:4326` (also known as `WGS 84`)
 *  4. Scroll down to the "Export" section
 *  5. Select `Proj4js` and copy the projection name and the definition
 * 
 * **NOTE:** most projections use two decimal coordinates generally referred to as `X` and `Y`. The `X` coordinate
 * goes into the `longitude` configuration options, while `Y` corresponds to `latitude`.
 * 
 * ## Troubleshooting
 * 
 * If no shapes appear on the map, check the JavaScript output. Proj4 conversion libraries will tell you, what is
 *  wrong or missing.
 * 
 * ### Try to remove unknown projection settings
 * 
 * For example, the definition of the Gauss-Kruger Zone 3 is 
 * 
 * ```
 * +proj=tmerc +lat_0=0 +lon_0=9 +k=1 +x_0=3500000 +y_0=0 +ellps=bessel +nadgrids=de_adv_BETA2007.tif +units=m +no_defs +type=crs
 * 
 * ```
 * However, it did not work, saying that it cannot find the mandatory grid `de_adv_BETA2007.tif`. The problem was
 * solved by removing `+nadgrids=de_adv_BETA2007.tif` from the definition.
 * 
 * Double-check the position on the map after manipulating the projection definition!
 * 
 * @author Andrej Kabachnik
 *
 */
class Proj4Projection implements MapProjectionInterface
{
    use ImportUxonObjectTrait;
    
    private string $name;
    private ?string $definition = null;
    
    /**
     * 
     * @param string $name
     * @param string $definition
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MapProjectionInterface::getName()
     */
    public function getName() : string
    {
        return $this->name;
    }
    
    /**
     * The EPSG projection name - e.g. `EPSG:31467`
     * 
     * @uxon-property name
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $value
     * @return MapProjectionInterface
     */
    protected function setName(string $value) : MapProjectionInterface
    {
        $this->name = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|null
     */
    public function getDefinition() : ?string
    {
        return $this->definition;
    }
    
    /**
     * The projection definition string in Proj4 format
     * 
     * E.g. `+proj=tmerc +lat_0=0 +lon_0=9 +k=1 +x_0=3500000 +y_0=0 +ellps=bessel +units=m +no_defs +type=crs` for
     * `EPSG:31467`
     * 
     * @uxon-property definition
     * @uxon-type string
     * 
     * @param string $proj4def
     * @return MapProjectionInterface
     */
    protected function setDefinition(string $proj4def) : MapProjectionInterface
    {
        $this->definition = $proj4def;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'name' => $this->name
        ]);
        if ($this->definition !== null) {
            $uxon->setProperty('definition', $this->definition);
        }
        return $uxon;
    }
}