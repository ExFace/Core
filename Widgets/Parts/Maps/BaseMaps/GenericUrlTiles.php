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
class GenericUrlTiles extends AbstractBaseMap
{
    private $url = null;
    
    public function __construct(Map $widget, UxonObject $uxon = null)
    {
        parent::__construct($widget, $uxon);
        $widget->getWorkbench()->eventManager()->addListener(OnFacadeWidgetRendererExtendedEvent::getEventName(), [$this, 'onLeafletRendererRegister']);
    }
    
    public function getUrl(string $default = '') : string
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
    protected function setUrl(string $value) : BaseMapInterface
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
            
            $url = $layer->getUrl();
            $url = str_replace('{a|b|c}', '{s}', $url);
            $attribution = json_encode($layer->getAttribution() ?? '');
            return <<<JS
L.tileLayer('{$url}', {
                    attribution: $attribution
                })
JS;
            
           
        });
    }
}