<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Widgets\Traits\iHaveColorTrait;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\Map;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\NumberDataType;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class GeoJSONLayer extends AbstractDataLayer implements iHaveColor
{
    use iHaveColorTrait;
    
    private $url = null;
    
    private $lineWeight = null;
    
    private $opacity = null;
    
    /**
     * 
     * @param Map $widget
     * @param UxonObject $uxon
     */
    public function __construct(Map $widget, UxonObject $uxon = null)
    {
        parent::__construct($widget, $uxon);
        $widget->getWorkbench()->eventManager()->addListener(OnFacadeWidgetRendererExtendedEvent::getEventName(), [$this, 'onLeafletRendererRegister']);
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
        
        if (! $facadeElement || ! method_exists($facadeElement, 'addLeafletLayerRenderer') || ! method_exists($facadeElement, 'addLeafletHeadTag')) {
            return;
        }
        
        $facadeElement->addLeafletHeadTag('<script src="' . $event->getFacade()->buildUrlToSource('LIBS.LEAFLET.ESRI.JS') . '"></script>');
        
        $facadeElement->addLeafletLayerRenderer(function(MapLayerInterface $layer) use ($facadeElement) {
            
            if ($layer !== $this) {
                return '';
            }
            
            $color = $this->getColor() ?? $facadeElement->getLayerBaseColor($this);
            $styleJs = "color: '$color',";
            if ($weight = $this->getLineWeight()) {
                $styleJs .= "weight: $weight,";
            }
            if ($opacity = $this->getOpacity()) {
                $styleJs .= "opacity: $opacity,";
            }
            
            
            
            return <<<JS
            
(function(){
    var oLayer = L.geoJSON(null, {
        onEachFeature: function (feature, layer) {
            var oPopupData = [];
            for (var prop in feature.properties) {
                oPopupData.push({
                    caption: prop,
                    value: feature.properties[prop]
                });
            }
            {$facadeElement->buildJsLeafletPopup("'{$this->getCaption()}'", $facadeElement->buildJsLeafletPopupList('oPopupData'), 'layer')}
        },
        style: { $styleJs },
        pointToLayer: function(feature, latlng) {
            var oProps = feature.properties;
            return L.marker(latlng, { 
                icon: new L.ExtraMarkers.icon({
                    icon: '',
                    markerColor: '$color',
                    shape: 'round',
                    prefix: 'fa',
                    svg: true,
                })
            });
        },      
    });

    oLayer._exfRefresh = function() {
        fetch('{$this->getUrl()}', {
            headers: {
                'Accept': 'application/geo+json,application/json'
            }
        })
        .then(function(response) {
            return response.json()
        })
        .then(function(data) {
            if (! Array.isArray(data) && Array.isArray(data.features)) {
                data = data.features;
            }
            oLayer.addData(data);
        });
    }

    {$facadeElement->buildJsLeafletVar()}.on('exfRefresh', oLayer._exfRefresh);
    oLayer._exfRefresh();

    return oLayer;
})()

JS;
        });
    }
    
    /**
     * 
     * @return string
     */
    public function getUrl() : string
    {
        if ($this->url === '' || $this->url === null) {
            throw new WidgetConfigurationError($this->getMap(), 'No url property specified for GeoJSON layer!');
        }
        
        return $this->url;
    }
    
    /**
     * The URL to fetch the GeoJSON - either absolute or relative to the workbench.
     * 
     * @uxon-property url
     * @uxon-type url
     * 
     * @param string $value
     * @return GeoJSONLayer
     */
    public function setUrl(string $value) : GeoJSONLayer
    {
        $this->url = $value;
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getLineWeight() : ?float
    {
        return $this->lineWeight;
    }
    
    /**
     * Weight (thikness) of the lines.
     * 
     * The accepted values depend on the facade used. However, pixels are very common:
     * try `5` for 5 pixels.
     * 
     * @uxon-property line_weight
     * @uxon-type number
     * 
     * @param float $value
     * @return GeoJSONLayer
     */
    public function setLineWeight(float $value) : GeoJSONLayer
    {
        $this->lineWeight = NumberDataType::cast($value);
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getOpacity() : ?float
    {
        return $this->opacity;
    }
    
    /**
     * Opacity of the layer: `1` for not transparent to `0` for invisible.
     * 
     * @uxon-property opacity
     * @uxon-type number
     * 
     * @param float $value
     * @return GeoJSONLayer
     */
    public function setOpacity(float $value) : GeoJSONLayer
    {
        $this->opacity = NumberDataType::cast($value);
        return $this;
    }
}