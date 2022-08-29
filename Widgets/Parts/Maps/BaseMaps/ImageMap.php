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
use exface\Core\Factories\WidgetLinkFactory;

/**
 * Allows to use an image (e.g. a construction plan) as a base map
 * 
 * ## Zooming
 * 
 * Consider the following example:
 * 
 * ```
 *  {
 *      "type": "ImageMap",
 *      "image_url":"vendor/my/app/Assets/Maps/map.jpg",
 *      "image_width": 2268,
 *      "image_height": 1604,
 *      "zoom_min": 1,
 *      "zoom_max": 4,
 *      "zoom_for_actual_size": 3
 *  }
 *  
 * ```
 * 
 * We want to be able to zoom over 4 levels (1 to 4). Zoom level 3 is going to be the actual size 
 * (1 to 1) of the image. That means zoom level 4 will be twice as big, zoom level 2 will half as 
 * big, and zoom level 1 a quarter of the original size.
 * 
 * A good explanation for these settings for leaflet.js is available here: https://kempe.net/blog/2014/06/14/leaflet-pan-zoom-image.html
 * 
 * @author Andrej Kabachnik
 *
 */
class ImageMap extends AbstractBaseMap
{
    private $url = null;
    
    private $urlLink = null;
    
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
     * The URL of the image or a link to a widget holding the url.
     * 
     * You can load images dynamically by using widget links: e.g. if you have an
     * `InputComboTable` with id `MapSelector` and a column named `MapUrl`, you can
     * use `=MapSelector!MapUrl` to load the image dynamically depending on the
     * selection in the combo.
     * 
     * @uxon-property url
     * @uxon-type url|metamodel:widget_link
     * 
     * @param string $value
     * @return GenericUrlTiles
     */
    protected function setImageUrl(string $value) : BaseMapInterface
    {
        if (Expression::detectReference($value)) {
            $this->urlLink = WidgetLinkFactory::createFromWidget($this->getMap(), $value);
        }
        $this->url = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isImageBoundToWidgetLink() : bool
    {
        return $this->urlLink !== null;
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
            if ($this->isImageBoundToWidgetLink()) {
                $link = $this->urlLink;
                $linkedEl = $facadeElement->getFacade()->getElement($link->getTargetWidget());
                $urlJs = $linkedEl->buildJsValueGetter($link->getTargetColumnId());
                $urlJs = '(' . $urlJs . ' || "")';
                $linkedEl->addOnChangeScript("$('#{$facadeElement->getIdLeaflet()}').data('_exfLeaflet').fire('exfRefresh');");
                $updateMapJs .= <<<JS
                    oMap.on('exfRefresh', function(){
                        var oLayer = {$facadeElement->buildJsBaseMapGetter($this, 'oMap')};
                        var sUrl = $urlJs;
                        oLayer.setUrl(sUrl);
                    });

JS;
            } else {
                $urlJs = json_encode($url);
                $updateMapJs = '';
            }
            
            $zoomOffset = ($this->getZoomMax() - $this->getZoomForActualSize());
            $initJs = <<<JS
                (function() {
                    var oMap = {$facadeElement->buildJsLeafletVar()};
                    var oBounds = new L.LatLngBounds(
                        oMap.unproject([0, {$this->getImageHeight()}], oMap.getMaxZoom()-$zoomOffset),
                        oMap.unproject([{$this->getImageWidth()}, 0], oMap.getMaxZoom()-$zoomOffset)
                    );
                    var oLayer;
                    oMap.setMaxBounds(oBounds);
                    oLayer = L.imageOverlay({$urlJs}, oBounds);
                    $updateMapJs
                    return oLayer;
                })()
            JS;
            
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