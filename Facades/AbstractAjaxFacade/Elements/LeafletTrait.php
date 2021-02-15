<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Widgets\Parts\Maps\DataMarkersLayer;

/**
 * This trait helps render Map widgets with Leaflet JS.
 * 
 * How to use: 
 * 
 * 1. Add the following dependency to the composer.json of the facade: "bower-asset/jquery-qrcode" : "^1.0"
 * 2. Add the config option "LIBS.QRCODE.JS": "bower-asset/jquery-qrcode/jquery.qrcode.min.js" to the facade
 * 3. Use the trait in your element and call buildHtmlQrCode() and buildJsQrCodeRenderer() where needed.
 * 
 * @method \exface\Core\Widgets\Map getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait LeafletTrait
{
    protected function buildHtmlLeafletDiv(string $heightCss) : string
    {
        return <<<HTML

        <div id="{$this->getIdLeaflet()}" class="{$this->buildCssClasses()}" style="height: {$heightCss}"></div>

HTML;
    }
    
    protected function buildJsLeafletInit() : string
    {
        $widget = $this->getWidget();
        
        $zoom = $widget->getZoom() ?? 2;
        $lat = $widget->getCenterLatitude() ?? 0;
        $lon = $widget->getCenterLongitude() ?? 0;
        
        return <<<JS

    {$this->buildJsLeafletVar()} = L.map('{$this->getIdLeaflet()}', {
        {$this->buildJsMapOptions()}
    }).setView([{$lat}, {$lon}], {$zoom});
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo({$this->buildJsLeafletVar()});

    {$this->buildJsLayers()}
    
JS;
    }
    
    protected function buildJsMapOptions() : string
    {
        $widget = $this->getWidget();
        
        $mapOptions = '';
        if ($widget->getShowFullScreenButton()) {
            $mapOptions .= "
        fullscreenControl: true,";
        }
        
        return $mapOptions;
    }
    
    protected function buildJsLayers() : string
    {
        $nameConstructorPairs = '';
        foreach ($this->getWidget()->getLayers() as $idx => $layer) {
            switch (true) {
                case $layer instanceof DataMarkersLayer: 
                    $nameConstructorPairs .= "'{$layer->getCaption()}' : {$this->buildJsDataMarkerLayer($layer, 'layer' . $idx)},";
                    break;
            }
        }
        
        return <<<JS

var aLayers = 
    
    L.control.layers(null, {
        $nameConstructorPairs
    }).addTo({$this->buildJsLeafletVar()});

JS;
    }
    
    protected function buildJsDataMarkerLayer(DataMarkersLayer $layer) : string
    {
        /* @var $dataWidget \exface\Core\Widgets\Data */
        $dataWidget = $layer->getDataWidget();
        $propertyId = $dataWidget->hasUidColumn() ? "'{$dataWidget->getUidColumn()->getDataColumnName()}'" : 'null';
        $propertyTitle = $layer->hasTooltip() ? "'{$layer->getTooltipColumn()->getDataColumnName()}'" : 'null';
        
        $markerProps = '';
        if ($layer->hasTooltip()) {
            $markerProps .= 'title: oRow.' . $layer->getTooltipColumn()->getDataColumnName() . ',';
        }
        
        return <<<JS
        (function(){
            var oBoundsInitial;
            var oLayer = L.layerJSON({
        		caching: true,				//disable markers caching and regenerate every time
                propertyId: $propertyId,
                propertyTitle: $propertyTitle,
                propertyLoc: ['{$layer->getLatitudeColumn()->getDataColumnName()}', '{$layer->getLongitudeColumn()->getDataColumnName()}'],
        		callData: function(bbox, callback) {
                    var oParams = {
                        resource: "{$dataWidget->getPage()->getAliasWithNamespace()}", 
                        element: "{$dataWidget->getId()}",
                        object: "{$dataWidget->getMetaObject()->getId()}",
                        action: "{$dataWidget->getLazyLoadingActionAlias()}"
                    };

                    {$this->buildJsDataLoadFunctionName()}(oParams)
                    .then(function(oResponseData){
        			    callback(oResponseData.rows || []);	//render data to layer
                    });
                    /*
        			return {
        				abort: function() {} //called to stop previous requests on map move
        			};*/
        		},
        		dataToMarker: function(oRow, latlng) {
                    return L.marker(latlng, { 
                        icon: {$this->buildJsMarkerIcon($layer, 'oRow')},
                        $markerProps 
                    });
        		}
        	});
            oLayer.on('dataloaded', function(e) {
            	setTimeout(function() {
                    if (oBoundsInitial === undefined) {
                        setTimeout(function(){
                            oBoundsInitial = oLayer.getBounds();
                            {$this->buildJsLeafletVar()}.fitBounds(oLayer.getBounds());
                        }, 0);
                    }
            	},100);
            });
            oLayer.addTo({$this->buildJsLeafletVar()});
            return oLayer; 
        })()
               
JS;
    }
    
    protected function buildJsMarkerIcon(DataMarkersLayer $layer, string $oRowJs) : string
    {
        $icon = $layer->getIcon() ?? '';
        $prefix = $layer->getIconSet() ?? 'fa';
        
        if ($layer->hasValue()) {
            $color = $layer->getColor() ?? 'black';
            return <<<JS
new L.ExtraMarkers.icon({
                            icon: 'fa-number',
                            number: {$oRowJs}.{$layer->getValueColumn()->getDataColumnName()},
                            markerColor: '$color',
                            shape: 'square',
                            svg: true,
                        })

JS;
        } else {
            $color = $layer->getColor() ?? 'black';
            return <<<JS
new L.ExtraMarkers.icon({
                            icon: '$icon',
                            markerColor: '$color',
                            shape: 'round',
                            prefix: '$prefix',
                            svg: true,
                        })
                        
JS;
        }
    }
    
    protected function buildJsLeafletVar() : string
    {
        return 'leaflet_' . $this->getId();
    }
    
    protected function getIdLeaflet() : string
    {
        return $this->getId();
    }
    
    public function buildCssClasses()
    {
        return 'exf-map';
    }
    
    protected function buildHtmlHeadTagsLeaflet() : array
    {
        $f = $this->getFacade();
        return [
            '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.CSS') . '"/>',
            '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.JS') . '"></script>',
            '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.LAYERJSON_JS') . '"></script>',
            '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.EXTRA_MARKERS_CSS') . '"/>',
            '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.EXTRA_MARKERS_JS') . '"></script>',
            '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.FULLSCREEN_CSS') . '"/>',
            '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.FULLSCREEN_JS') . '"></script>'
        ]; 
    }
}