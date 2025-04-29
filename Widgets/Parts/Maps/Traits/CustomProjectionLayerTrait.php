<?php
namespace exface\Core\Widgets\Parts\Maps\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapProjectionInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Projection\Proj4Projection;

/**
 * This trait adds properties to set a custom EPSG projection definition
 * 
 * @author Andrej Kabachnik
 *
 */
trait CustomProjectionLayerTrait
{
    private $projection = null;
    
    private $projectionConfig = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\CustomProjectionMapLayerInterface::getProjection()
     */
    public function getProjection() : MapProjectionInterface
    {
        return $this->projection;
    }
    
    /**
     * The projection to be used - e.g. `EPSG:25832` or `EPSG:3857` - or empty for autodetection.
     *
     * See https://epsg.io/ for more information about projections and their definitions.
     * 
     * The default projection is `EPSG:4326` (also known as `WGS 84`).
     *
     * Example:
     *
     * ```
     * {
     *  "projection": {
     *      "name": "EPSG:25832",
     *      "definition": "+proj=utm +zone=32 +ellps=GRS80 +datum=NAD83 +units=m +no_defs"
     *  }
     * }
     *
     * ```
     *
     * @uxon-property projection
     * @uxon-type \exface\Core\Widgets\Parts\Maps\Projection\Proj4Projection
     * @uxon-template {"name": "", "definition": ""}
     *
     * @param string $value
     * @return MapLayerInterface
     */
    protected function setProjection(UxonObject $value) : MapLayerInterface
    {
        $this->projection = new Proj4Projection($value->getProperty('name'));
        $this->projection->importUxonObject($value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\CustomProjectionMapLayerInterface::hasProjectionDefinition()
     */
    public function hasProjectionDefinition() : bool
    {
        return $this->projection !== null;
    }
}