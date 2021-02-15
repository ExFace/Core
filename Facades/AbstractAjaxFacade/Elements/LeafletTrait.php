<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Widgets\Parts\Maps\DataMarkersLayer;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;
use exface\Core\Interfaces\WidgetInterface;

/**
 * This trait helps render Map widgets with Leaflet JS.
 * 
 * How to use: 
 * TODO
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
    private $layerRenderers = [];
    
    private $headTags = [];
    
    public function addLeafletLayerRenderer(callable $callback)
    {
        $this->layerRenderers[] = $callback;
        return;
    }
    
    public function addLeafletHeadTag(string $html)
    {
        $this->headTags[] = $html;
        return;
    }
    
    /**
     * @return void
     */
    protected function registerDefaultLayerRenderers()
    {
        $this->addLeafletLayerRenderer([$this, 'buildJsDataMarkerLayer']);
        return;
    }
    
    protected function fireRendererExtendedEvent(WidgetInterface $widget) 
    {
        $widget->getWorkbench()->eventManager()->dispatch(new OnFacadeWidgetRendererExtendedEvent($this->getFacade(), $widget, $this));
        return;
    }
    
    /**
     * 
     * @param string $heightCss
     * @return string
     */
    protected function buildHtmlLeafletDiv(string $heightCss) : string
    {
        return <<<HTML

        <div id="{$this->getIdLeaflet()}" class="{$this->buildCssClasses()}" style="height: {$heightCss}"></div>

HTML;
    }
    
    /**
     * 
     * @return string
     */
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

    {$this->buildJsLocateControl()}
    {$this->buildJsScaleControl()}

    {$this->buildJsLayers()}
    
JS;
    }
    
    /**
     * 
     * @return string
     */
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
    
    /**
     * 
     * @return string
     */
    protected function buildJsLocateControl() : string
    {
        if (! $this->getWidget()->getShowGpsLocateButton()) {
            return '';
        }
        return "L.control.locate().addTo({$this->buildJsLeafletVar()});";
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsScaleControl() : string
    {
        if (! $this->getWidget()->getShowScale()) {
            return '';
        }
        return "L.control.scale().addTo({$this->buildJsLeafletVar()});";
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsLayers() : string
    {
        $baseMaps = '';
        $baseSelected = $this->getWidget()->getBaseMap(0);
        foreach ($this->getWidget()->getBaseMaps() as $idx => $layer) {
            $captionJs = json_encode($layer->getCaption() ?? 'Map ' . ($idx+1));
            $visible = ($baseSelected === $layer);
            $layerInit = $this->buildJsLayer($layer);
            if ($layerInit) {
                $baseMaps .= "{$captionJs} : {$layerInit}";
                if ($visible) {
                    $baseMaps .= ".addTo({$this->buildJsLeafletVar()})";
                }
                $baseMaps .= ',';
            }
        }
        
        $featureLayers = '';
        foreach ($this->getWidget()->getLayers() as $layer) {
            $captionJs = json_encode($layer->getCaption());
            $visible = true;
            $layerInit = $this->buildJsLayer($layer);
            if ($layerInit) {
                $featureLayers .= "{$captionJs} : {$layerInit}";
                if ($visible) {
                    $featureLayers .= ".addTo({$this->buildJsLeafletVar()})";
                }
                $featureLayers .= ',';
            }
        }
        
        return <<<JS

    var aLayers = L.control.layers({
            $baseMaps
        }, {
            $featureLayers
        }
    ).addTo({$this->buildJsLeafletVar()});

JS;
    }
    
    /**
     * 
     * @param MapLayerInterface $layer
     * @param int $index
     * @return string
     */
    protected function buildJsLayer(MapLayerInterface $layer) : string
    {
        $renderers = array_reverse($this->layerRenderers);
        foreach ($renderers as $callable) {
            $js = $callable($layer, $this);
            if ($js) {
                break;
            }
        }
        return $js ?? '';
    }
    
    /**
     * 
     * @param DataMarkersLayer $layer
     * @return string
     */
    protected function buildJsDataMarkerLayer(MapLayerInterface $layer) : ?string
    {
        if (! ($layer instanceof DataMarkersLayer)) {
            return null;
        }
        
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
        $widget = $this->getWidget();
        $f = $this->getFacade();
        $includes = [
            '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.CSS') . '"/>',
            '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.JS') . '"></script>',
            '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.LAYERJSON_JS') . '"></script>',
            '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.EXTRA_MARKERS_CSS') . '"/>',
            '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.EXTRA_MARKERS_JS') . '"></script>'
        ]; 
        
        if ($widget->getShowFullScreenButton()) {
            $includes[] = '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.FULLSCREEN_CSS') . '"/>';
            $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.FULLSCREEN_JS') . '"></script>';
        }
        
        if ($widget->getShowGpsLocateButton()) {
            $includes[] = '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.LOCATECONTROL_CSS') . '"/>';
            $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.LOCATECONTROL_JS') . '"></script>';
        }
        
        $includes = array_merge($includes, array_unique($this->headTags));
        
        return $includes;
    }
}