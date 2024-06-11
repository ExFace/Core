<?php
namespace exface\Core\Widgets\Parts\Maps\Projection;

use exface\Core\Widgets\Parts\Maps\Interfaces\MapProjectionInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;

/**
 * Allows to use custom projections according to the proj4 definition format
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
 * 1. Go to https://epsg.io/
 * 2. Search for the desired coordinat system
 * 3. Under "Available transformations" pick `EPSG:4326` (also known as `WGS 84`)
 * 4. Scroll down to the "Export" section
 * 5. Select `Proj4js` and copy the projection name and the defintion
 * 
 * @author andrej.kabachnik
 *
 */
class Proj4Projection implements MapProjectionInterface
{
    use ImportUxonObjectTrait;
    
    private $name = null;
    
    private $definition = null;
    
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
     * @return string
     */
    public function getDefinition() : string
    {
        return $this->definition;
    }
    
    /**
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
        return new UxonObject();
    }
}
