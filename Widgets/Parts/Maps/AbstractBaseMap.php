<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface;
use exface\Core\Widgets\Map;

/**
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractBaseMap extends AbstractMapLayer implements BaseMapInterface
{
    private $attribution = null;
    
    private $maxZoom = null;
    
    private $minZoom = 0;
    
    /**
     *
     * @return string|NULL
     */
    public function getAttribution() : ?string
    {
        return $this->attribution;
    }
    
    /**
     * Changes the attribution shown on the map (accepts HTML).
     *
     * @uxon-property attribution
     * @uxon-type string
     *
     * @param string $value
     * @return BaseMapInterface
     */
    public function setAttribution(string $value) : BaseMapInterface
    {
        $this->attribution = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractMapPart::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO
        return $uxon;
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getZoomMax() : ?int
    {
        return $this->maxZoom;
    }
    
    /**
     * The maximum zoom level down to which this layer will be displayed (inclusive).
     * 
     * @uxon-property zoom_max
     * @uxon-type integer
     * 
     * @param int $value
     * @return AbstractBaseMap
     */
    protected function setZoomMax(int $value) : AbstractBaseMap
    {
        $this->maxZoom = $value;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getZoomMin() : int
    {
        return $this->minZoom;
    }
    
    /**
     * The minimum zoom level down to which this layer will be displayed (inclusive).
     * 
     * @uxon-property zoom_min
     * @uxon-type integer
     * @uxon-default 0
     * 
     * @param int $value
     * @return AbstractBaseMap
     */
    protected function setZoomMin(int $value) : AbstractBaseMap
    {
        $this->minZoom = $value;
        return $this;
    }
    
    protected function buildJsPropertyZoom() : string
    {
        $zoom = '';
        if (null !== $val = $this->getZoomMax()) {
            $zoom .= 'zoomMax: ' . $val . ',';
        }
        if (0 !== $val = $this->getZoomMin()) {
            $zoom .= 'zoomMin: ' . $val . ',';
        }
        return $zoom;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface::getCoordinateSystem()
     */
    public function getCoordinateSystem() : string
    {
        return Map::COORDINATE_SYSTEM_AUTO;
    }
}