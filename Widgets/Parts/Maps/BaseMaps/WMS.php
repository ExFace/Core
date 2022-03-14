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
 * Generic base map for WMS map sources (Web Map Service)
 * 
 * ## Example
 * 
 * ```
 *  {
 *      "type": "WMS",
 *      "caption": "TopPlus-Open (BKG)",
 *      "url": "https://sgx.geodatenzentrum.de/wms_topplus_web_open?",
 *      "layers": "web",
 *      "transparent": true,
 *      "format": "image/png",
 *      "attribution": "Darstellungsdienst für weltweite einheitliche Webkarte",
 *      "zoom_max": 20
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class WMS extends GenericUrlTiles
{
    private $url = null;
    
    private $format = null;
    
    private $layers = null;
    
    private $transparent = false;
    
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
            
            $url = $layer->getUrl();
            $attribution = json_encode($layer->getAttribution() ?? '');
            $transparent = $this->getTransparent() ? 'true' : 'false';
            return <<<JS
L.tileLayer.wms('{$url}', {
                    attribution: $attribution,
                    layers: '{$this->getLayers()}',
        			format: '{$this->getFormat()}',
        			transparent: {$transparent},
                    {$this->buildJsPropertyZoom()}
                })
JS;
            
           
        });
    }
    
    /**
     * 
     * @return string
     */
    public function getLayers() : string
    {
        return $this->layers;
    }
    
    /**
     * Comma-separated list of WMS layers to show
     * 
     * @uxon-property layers
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $value
     * @return WMS
     */
    protected function setLayers(string $value) : WMS
    {
        $this->layers = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getFormat(string $default = 'image/jpeg') : string
    {
        return $this->format ?? $default;
    }
    
    /**
     * The mime type of the map data - e.g. image/png or image/jpeg.
     * 
     * @uxon-property format
     * @uxon-type string
     * @uxon-default image/jpeg
     * 
     * @param string $value
     * @return WMS
     */
    protected function setFormat(string $value) : WMS
    {
        $this->format = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getTransparent() : bool
    {
        return $this->transparent;
    }
    
    /**
     * Set to TRUE to make the WMS service return images with transparency.
     * 
     * @uxon-property transparent
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return WMS
     */
    protected function setTransparent(bool $value) : WMS
    {
        $this->transparent = $value;
        return $this;
    }
}