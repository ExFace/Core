<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\Map;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Interfaces\Widgets\iHaveColorScale;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonMapLayerInterface;
use exface\Core\Widgets\Traits\iHaveColorTrait;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsValueScaleTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\CustomProjectionMapLayerInterface;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Widgets\Parts\Maps\Projection\Proj4Projection;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class GeoJSONLayer extends AbstractMapLayer implements GeoJsonMapLayerInterface, iHaveColor, iHaveColorScale
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
            
            return $this->buildJsLayerGeoJson($layer, $facadeElement);
        });
    }
    
    protected function buildJsLayerGeoJson(MapLayerInterface $layer, AjaxFacadeElementInterface $facadeElement)
    {
        if (! ($layer instanceof GeoJsonMapLayerInterface)) {
            return null;
        }
        
        $color = $layer->getColor() ?? $facadeElement->getLayerBaseColor($layer);
        $styleJs = "";
        $colorScaleJs = '';
        if ($weight = $layer->getLineWeight()) {
            $styleJs .= "weight: $weight,";
        }
        if ($opacity = $layer->getOpacity()) {
            $styleJs .= "opacity: $opacity,";
        }
        if ($layer->hasColorScale()) {
            $scaleProp = $layer->getColorScaleProperty();
            if ($scaleProp === null) {
                throw new WidgetConfigurationError($layer->getMap(), 'Missing map layer option color_scale_property: A GeoJSON map layer with a color_scale must know, which property of the features to use a scale base!');
            }
            $colorScaleJs .= "oStyle.color = " . $this->buildJsScaleResolver('feature.properties.' . $scaleProp, $this->getColorScale(), $this->isColorScaleRangeBased());
        } else {
            $styleJs .= "color: '$color',";
        }
        
        $styleFuncJs = <<<JS
        
            var oStyle = { {$styleJs} };
            $colorScaleJs
            return oStyle;
            
JS;
            // Add auto-zoom
            if ($layer->getAutoZoomToSeeAll() === true || $layer->getAutoZoomToSeeAll() === null && count($this->getWidget()->getDataLayers()) === 1){
                $autoZoomJs = $facadeElement->buildJsAutoZoom('oLayer', $layer->getAutoZoomMax());
            }
            
            if (($layer instanceof CustomProjectionMapLayerInterface) && $layer->hasProjectionDefinition() && $layer->getProjection() instanceof Proj4Projection) {
                $proj = $layer->getProjection();
                $projectionInit = "proj4.defs('{$proj->getName()}', '{$proj->getDefinition()}');";
                $layerConstructor = 'L.Proj.geoJson';
            } else {
                $layerConstructor = 'L.geoJSON';
            }
            
            return <<<JS
            
(function(){
    $projectionInit
    
    var oLayer = $layerConstructor(null, {
        onEachFeature: function (feature, layer) {
            var oPopupData = [];
            for (var prop in feature.properties) {
                oPopupData.push({
                    caption: prop,
                    value: feature.properties[prop]
                });
            }
            {$facadeElement->buildJsLeafletPopup("'{$layer->getCaption()}'", $facadeElement->buildJsLeafletPopupList('oPopupData'), 'layer')}
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
        {$this->buildJsLayerGeoJsonLoader($layer, 'aFeatures', <<<JS
            
        oLayer.clearLayers();
        oLayer.addData(aFeatures);
            
        {$autoZoomJs}
        
JS, '')}
    }
    
    {$facadeElement->buildJsLeafletVar()}.on('exfRefresh', oLayer._exfRefresh);
    oLayer._exfRefresh();
    
    return oLayer;
})()

JS;
    }
    
    /**
     * 
     * @param MapLayerInterface $layer
     * @param string $aFeaturesJs
     * @param string $onLoadedJs
     * @param string $onErrorJs
     * @return string
     */
    protected function buildJsLayerGeoJsonLoader(MapLayerInterface $layer, string $aFeaturesJs, string $onLoadedJs, string $onErrorJs) : string
    {
        return <<<JS

        fetch('{$this->getUrl()}', {
            headers: {
                'Accept': 'application/geo+json,application/json'
            }
        })
        .then(function(response) {
            return response.json()
        })
        .then(function(data) {
            var $aFeaturesJs;
            if (! Array.isArray(data) && Array.isArray(data.features)) {
                $aFeaturesJs = data.features;
            } else {
                $aFeaturesJs = data;
            }
            $onLoadedJs
        });

JS;
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