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
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsValueScaleTrait;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class GeoJSONLayer extends AbstractDataLayer implements iHaveColor, iHaveColorScale
{
    use iHaveColorTrait;
    
    use iHaveColorScaleTrait;
    
    use JsValueScaleTrait;
    
    private $url = null;
    
    private $lineWeight = null;
    
    private $opacity = null;
    
    private $colorScaleProperty = null;
    
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
            $styleJs = "";
            $colorScaleJs = '';
            if ($weight = $this->getLineWeight()) {
                $styleJs .= "weight: $weight,";
            }
            if ($opacity = $this->getOpacity()) {
                $styleJs .= "opacity: $opacity,";
            }
            if ($this->hasColorScale()) {
                $scaleProp = $this->getColorScaleProperty();
                if ($scaleProp === null) {
                    throw new WidgetConfigurationError($this->getMap(), 'Missing map layer option color_scale_property: A GeoJSON map layer with a color_scale must know, which property of the features to use a scale base!');
                }
                $colorScaleJs = "oStyle.color = {$this->buildJsScaleResolver('feature.properties.' . $scaleProp, $this->getColorScale(), $this->isColorScaleRangeBased())}";
            } else {
                $styleJs .= "color: '$color',";
            }
            
            $styleFuncJs = <<<JS

            var oStyle = { {$styleJs} };
            $colorScaleJs
            return oStyle;

JS;
            
            if ($layer->getAutoZoomToSeeAll() === true){
                $autoZoomJs = $facadeElement->buildJsAutoZoom('oLayer');
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
        style: function(feature) {
            $styleFuncJs
        },
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

            {$autoZoomJs}
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
    protected function getLineWeight() : ?float
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
    protected function getOpacity() : ?float
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColorScale::isColorScaleRangeBased()
     */
    public function isColorScaleRangeBased(): bool
    {
        foreach (array_values($this->getColorScale()) as $val) {
            if ($val !== null && $val !== '' && ! is_numeric($val)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getColorScaleProperty() : ?string
    {
        return $this->colorScaleProperty;
    }
    
    /**
     * The name of the feature property to use as base for coloring.
     * 
     * Accepts a JavaScript-type path from the `properties` of a GeoJSON feature.
     * 
     * **NOTE**: every Feature MUST have this property!
     * 
     * For the example GeoJSON below you could use `status` or `metrics.progress`
     * as `color_scale_property`.
     * 
     * ```
     * {
     *      type: "Feature",
     *      geometry: {...},
     *      properties: {
     *          id: 123,
     *          name: "Feature name"
     *          status: "Good",
     *          metrics: {
     *              progress: 95,
     *          }
     *      }
     * }          
     * 
     * ```
     * 
     * @param string $value
     * @return GeoJSONLayer
     */
    public function setColorScaleProperty(string $value) : GeoJSONLayer
    {
        $this->colorScaleProperty = $value;
        return $this;
    }
}