<?php
namespace exface\Core\Widgets\Parts\Maps\BaseMaps;

use exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\Maps\AbstractBaseMap;
use exface\Core\Widgets\Map;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;
use exface\Core\CommonLogic\Model\Expression;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class ImageMap extends AbstractBaseMap
{
    private $url = null;
    
    private $imageHeight = null;
    
    private $imageWidth = null;
    
    private $zoomForActualSize = null;
    
    public function __construct(Map $widget, UxonObject $uxon = null)
    {
        parent::__construct($widget, $uxon);
        $widget->getWorkbench()->eventManager()->addListener(OnFacadeWidgetRendererExtendedEvent::getEventName(), [$this, 'onLeafletRendererRegister']);
        
        if ($widget->getZoomMin() === null) {
            $widget->setZoomMin($this->getZoomMin());
        }
        if ($widget->getZoomMax() === null) {
            $widget->setZoomMax($this->getZoomMax());
        }
        if ($widget->getZoom() === null) {
            $widget->setZoom($this->getZoomMin());
        }
    }
    
    /**
     * 
     * @param string $default
     * @throws WidgetConfigurationError
     * @return string
     */
    public function getImageUrl(string $default = '') : string
    {
        $url = $this->url ?? $default;
        if ($url === '') {
            throw new WidgetConfigurationError($this->getMap(), 'Invalid configuration for base map "' . $this->getCaption() . '": no URL to get tiles from provided!');
        }
        return $url;
    }
    
    /**
     * The URL to get the tiles from.
     * 
     * Accepts any format compatible with the facade used to render the map. OpenStreetMap-style 
     * tile URLs are very common: https://wiki.openstreetmap.org/wiki/Tiles#Base_maps.
     * 
     * @uxon-property url
     * @uxon-type url
     * 
     * @param string $value
     * @return GenericUrlTiles
     */
    protected function setImageUrl(string $value) : BaseMapInterface
    {
        $this->url = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isImageBoundToWidgetLink() : bool
    {
        return $this->url === null ? false : Expression::detectReference($this->url);
    }
    
    /**
     * 
     * @param OnFacadeWidgetRendererExtendedEvent $event
     */
    public function onLeafletRendererRegister(OnFacadeWidgetRendererExtendedEvent $event)
    {
        if ($event->getWidget() !== $this->getMap()) {
            return;
        }
        
        $facadeElement = $event->getFacadeElement();
        
        if (! $facadeElement || ! method_exists($facadeElement, 'addLeafletLayerRenderer')) {
            return;
        }
        
        $facadeElement->addLeafletLayerRenderer(function(MapLayerInterface $layer) use ($facadeElement) {
        
            
            if ($layer !== $this) {
                return '';
            }
            
            $url = $layer->getImageUrl();
            
            if (Expression::detectReference($url)) {
                $initJs = <<<JS
(function(){
    
})()

JS;
            } else {
                $zoomOffset = ($this->getZoomMax() - $this->getZoomForActualSize());
                $initJs = <<<JS
(function() {
    var oMap = {$facadeElement->buildJsLeafletVar()};
    var oBounds = new L.LatLngBounds(
        oMap.unproject([0, {$this->getImageHeight()}], oMap.getMaxZoom()-$zoomOffset),
        oMap.unproject([{$this->getImageWidth()}, 0], oMap.getMaxZoom()-$zoomOffset)
    );
    oMap.setMaxBounds(oBounds);
    return L.imageOverlay('{$url}', oBounds);
})()
JS;
            }
            return $initJs;
        });
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface::getCoordinateSystem()
     */
    public function getCoordinateSystem() : string
    {
        return Map::COORDINATE_SYSTEM_PIXELS;
    }
    
    /**
     * 
     * @return int
     */
    public function getImageHeight() : int
    {
        return $this->imageHeight;
    }
    
    /**
     * The height of the image in pixels
     * 
     * @uxon-property image_height
     * @uxon-type integer
     * @uxon-required true
     * 
     * @param int $value
     * @return ImageMap
     */
    protected function setImageHeight(int $value) : ImageMap
    {
        $this->imageHeight = $value;
        return $this;
    }
    
    /**
     * 
     * @return int
     */
    public function getImageWidth() : int
    {
        return $this->imageWidth;
    }
    
    /**
     * The widget of the image in pixels
     * 
     * @uxon-property image_width
     * @uxon-type integer
     * @uxon-required true
     * 
     * @param int $value
     * @return ImageMap
     */
    protected function setImageWidth(int $value) : ImageMap
    {
        $this->imageWidth = $value;
        return $this;
    }
    
    /**
     * 
     * @return int
     */
    public function getZoomForActualSize() : int
    {
        return $this->zoomForActualSize ?? 3;
    }
    
    /**
     * Zoom level at which the image is to be at 1:1 scale
     * 
     * @uxon-property zoom_for_actual_size
     * @uxon-type integer
     * @uxon-default 3
     * 
     * @param int $value
     * @return ImageMap
     */
    public function setZoomForActualSize(int $value) : ImageMap
    {
        $this->zoomForActualSize = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractBaseMap::getZoomMax()
     */
    public function getZoomMax() : ?int
    {
        return parent::getZoomMax() ?? 4;
    }
}