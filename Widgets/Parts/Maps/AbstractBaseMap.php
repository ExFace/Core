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

    private $doubleClickToZoom = true;
    
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
     * @return boolean
     */
    public function getDoubleClickToZoom() : ?int
    {
        return $this->doubleClickToZoom;
    }

    /**
     * Enable double click to zoom feature on map
     * 
     * @uxon-property double_click_to_zoom
     * @uxon-type boolean
     * @uxon-defaul true
     * 
     * @param int $value
     * @return Map
     */
    protected function setDoubleClickToZoom(int $value) : AbstractBaseMap
    {
        $this->doubleClickToZoom = $value;
        return $this;
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption() : ?string
    {
        return parent::getCaption() ?? 'Map ' . ($this->getMap()->getBaseMapIndex($this) + 1);
    }
}