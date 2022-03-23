<?php
namespace exface\Core\Widgets\Parts\Maps\BaseMaps;

use exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\Maps\AbstractBaseMap;
use exface\Core\Widgets\Map;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;

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
    
    public function __construct(Map $widget, UxonObject $uxon = null)
    {
        parent::__construct($widget, $uxon);
        $widget->getWorkbench()->eventManager()->addListener(OnFacadeWidgetRendererExtendedEvent::getEventName(), [$this, 'onLeafletRendererRegister']);
        
        if ($widget->getZoomMin() === null) {
            $widget->setZoomMin(1);
        }
        if ($widget->getZoomMax() === null) {
            $widget->setZoomMax(4);
        }
        if ($widget->getZoom() === null) {
            $widget->setZoom(1);
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
        
        $facadeElement->addLeafletLayerRenderer(function(MapLayerInterface $layer){
        
            
            if ($layer !== $this) {
                return '';
            }
            
            $url = $layer->getImageUrl();
            $url = str_replace('{a|b|c}', '{s}', $url);
            return <<<JS
L.imageOverlay('{$url}', (
    new L.LatLngBounds(
        leaflet_Map.unproject([0, {$this->getImageHeight()}], leaflet_Map.getMaxZoom()-1),
        leaflet_Map.unproject([{$this->getImageWidth()}, 0], leaflet_Map.getMaxZoom()-1)
    )
))
JS;
            
           
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
}