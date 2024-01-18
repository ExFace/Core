<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Traits\iHaveColorTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\Traits\iHaveColorScaleTrait;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonMapLayerInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
trait LeafletGeoJsonTrait
{
    use iHaveColorTrait;
    
    use iHaveColorScaleTrait;
    
    use JsValueScaleTrait;
    
    private $lineWeight = null;
    
    private $opacity = null;
    
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
            $colorScaleJs .= "oStyle.color = {$this->buildJsLayerGeoJsonColor($layer)}";
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
     * 
     * @return string
     */
    abstract protected function buildJsLayerGeoJsonLoader(MapLayerInterface $layer, string $aFeaturesJs, string $onLoadedJs, string $onErrorJs) : string;
    
    abstract protected function buildJsLayerGeoJsonColor(MapLayerInterface $layer) : string;
}