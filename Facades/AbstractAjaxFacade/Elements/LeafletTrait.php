<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Widgets\Parts\Maps\DataMarkersLayer;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\Widgets\Parts\Maps\AbstractDataLayer;
use exface\Core\DataTypes\WidgetVisibilityDataType;

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
    
    protected function initLeaflet()
    {
        $this->fireRendererExtendedEvent($this->getWidget());
        $this->registerDefaultLayerRenderers();
        return;
    }
    
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

        <div id="{$this->getIdLeaflet()}" class="{$this->buildCssElementClass()}" style="height: {$heightCss}"></div>

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

(function(){
    {$this->buildJsLeafletVar()} = L.map('{$this->getIdLeaflet()}', {
        {$this->buildJsMapOptions()}
    })
    .setView([{$lat}, {$lon}], {$zoom})
    .on('contextmenu', function(e) {
        var latlng = e.latlng;
        var layer = e.target;
        {$this->buildJsLeafletPopup('"Location info"', $this->buildJsLeafletPopupList("[
            {
                caption: 'Latitude',
                value: latlng.lat + '°'
            },{
                caption: 'Longitude',
                value: latlng.lng + '°'
            },{
                caption: 'Altitude',
                value: latlng.alt ? latlng.alt + 'm' : '?'
            }
        ]"), "[latlng.lat,latlng.lng]")}
    });

    {$this->buildJsLeafletVar()}._exfState = {
        selectedFeature: null
    };
    {$this->buildJsLeafletVar()}._exfLayers = {};

    {$this->buildJsLeafletControlLocate()}
    {$this->buildJsLeafletControlScale()}

    {$this->buildJsLayers()}
})();

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
    
    protected function buildJsLeafletControlInfo() : string
    {
        return <<<JS

    L.easyButton({
        states: [
            {
                stateName: 'click-mode-regular',        // name the state
                icon:      'fa-mouse-pointer',               // and define its properties
                title:     'Display place information on click',      // like its title
                onClick: function(btn, map) {       // and its callback
                    map.on('click', map._exfShowInfoPopup);
                    btn.state('click-mode-info');    // change state on click!
                }
            }, {
                stateName: 'click-mode-info',
                icon:      'fa-hand-paper-o',
                title:     'Back to regular mouse mode',
                onClick: function(btn, map) {
                    map.off('click', map._exfShowInfoPopup);
                    btn.state('click-mode-regular');
                }
            }
        ]
    }).addTo({$this->buildJsLeafletVar()});

JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsLeafletControlLocate() : string
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
    protected function buildJsLeafletControlScale() : string
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
        $featureMetadata = '';
        foreach ($this->getWidget()->getLayers() as $index => $layer) {
            $captionJs = json_encode($layer->getCaption());
            $visible = true;
            $layerInit = $this->buildJsLayer($layer);
            if ($layerInit) {
                $featureLayers .= "{$captionJs} : {$layerInit}";
                if ($visible) {
                    $featureLayers .= ".addTo({$this->buildJsLeafletVar()})";
                }
                $featureLayers .= ',';
                if ($layer instanceof AbstractDataLayer) {
                    $featureMetadata .= <<<JS
{$index} : {
       oId : {$layer->getMetaObject()->getId()}
},    

JS;
                }
            }
        }
        
        return <<<JS

    var aLayers = L.control.layers({
            $baseMaps
        }, {
            $featureLayers
        }
    ).addTo({$this->buildJsLeafletVar()});

    {$this->buildJsLeafletVar()}._exfLayers = { {$featureMetadata} };

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
    
    public function buildJsLeafletPopup(string $titleJs, string $contentJs, string $bindToJs) : string
    {
        return <<<JS

                        (function() {
                            var sTitle = $titleJs;
                            var sContent = (sTitle ? '<h3>' + $titleJs + '</h3>' : '') + $contentJs;
                            if (Array.isArray($bindToJs)){
                                L.popup({
                                    className: "exf-map-popup"
                                })
                                    .setLatLng($bindToJs)
                                    .setContent(sContent)
                                    .openOn({$this->buildJsLeafletVar()});
                            } else {
                                $bindToJs.bindPopup(sContent, {
                                    className: "exf-map-popup"
                                });
                            }
                        })();
JS;
    }
    
    public function buildJsLeafletPopupList(string $aRowsJs) : string
    {
        return <<<JS

                            (function(){
                                var sHtml = '';
                                var aRows = $aRowsJs || [];
                                aRows.forEach(function(oRow){
                                    if (! oRow) return;
                                    sHtml += '<tr class="' + (oRow.class || '') + '" title="' + (oRow.tooltip || '') + '"><td>' + oRow.caption + ':</td><td>' + oRow.value + '</td></tr>';
                                });
                                if (sHtml !== '') {
                                    sHtml = '<table class="exf-map-popup-table">' + sHtml + '</table>';
                                }
                                return sHtml;
                            })()

JS;
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
        
        $markerProps = '';
        if ($layer->hasTooltip()) {
            $markerProps .= 'title: oRow.' . $layer->getTooltipColumn()->getDataColumnName() . ',';
        }
        
        $popupTableRowsJs = '';
        $popupCaptionJs = json_encode($layer->getCaption());
        foreach ($dataWidget->getColumns() as $col) {
            if ($col->isHidden() === false) {
                $visibility = strtolower(WidgetVisibilityDataType::findKey($col->getVisibility()));
                $hint = json_encode($col->getHint() ?? '');
                $caption = json_encode($col->getCaption() ?? '');
                $popupTableRowsJs .= "{class: \"exf-{$visibility}\", tooltip: $hint, caption: $caption, value: feature.properties.data['{$col->getDataColumnName()}'] },";
            }
        }
        
        $showPopupJs = $this->buildJsLeafletPopup($popupCaptionJs, $this->buildJsLeafletPopupList("[$popupTableRowsJs]"), 'layer');
                     
        if ($layer->getAutoZoomToSeeAll() === true || $layer->getAutoZoomToSeeAll() === null && count($this->getWidget()->getDataLayers()) === 1){
            $autoZoomJs = $this->buildJsAutoZoom('oLayer');
        }
        
        return <<<JS
            (function(){
                var oLayer = L.geoJSON(null, {
                    pointToLayer: function(feature, latlng) {
                        var oRow = feature.properties.data;
                        return L.marker(latlng, { 
                            icon: {$this->buildJsMarkerIcon($layer, 'oRow')},
                            $markerProps 
                        });
                    },
                    onEachFeature: function(feature, layer) {
                        {$showPopupJs}                       

                        // Toggle marker selected state
                        layer.on('click', function (e) {
                            var jqIcon = $(e.target.getElement());
                            if (jqIcon.hasClass('selected')) {
                                $('#{$this->getIdLeaflet()} .leaflet-marker-icon').removeClass('selected');
                                {$this->buildJsLeafletVar()}._exfState.selectedFeature = null;
                            } else {
                                $('#{$this->getIdLeaflet()} .leaflet-marker-icon').removeClass('selected');
                                {$this->buildJsLeafletVar()}._exfState.selectedFeature = feature;
                                jqIcon.addClass('selected');
                            }
                        });
                    
                    }
                });

                oLayer._exfRefresh = function() {
                    var oParams = {
                        resource: "{$dataWidget->getPage()->getAliasWithNamespace()}", 
                        element: "{$dataWidget->getId()}",
                        object: "{$dataWidget->getMetaObject()->getId()}",
                        action: "{$dataWidget->getLazyLoadingActionAlias()}",
                        data: {
                            oId: "{$dataWidget->getMetaObject()->getId()}"
                        }
                    };
    
                    {$this->buildJsLeafletDataLoader('oParams', 'aRows', "

                        var aGeoJson = [];
                        var aRowsSkipped = [];
                    
                        aRows.forEach(function(oRow){
                            var fLat = parseFloat(oRow.{$layer->getLatitudeColumn()->getDataColumnName()});
                            var fLng = parseFloat(oRow.{$layer->getLongitudeColumn()->getDataColumnName()});
        
                            if (isNaN(fLat) || isNaN(fLng)) {
                                aRowsSkipped.push(oRow);
                                return;
                            }
    
                            aGeoJson.push({
                                type: 'Feature',
                                geometry: {
                                    type: 'Point',
                                    coordinates: [fLng, fLat],
                                },
                                properties: {
                                    layer: {$this->getWidget()->getLayerIndex($layer)},
                                    object: '{$layer->getMetaObject()->getId()}',
                                    data: oRow
                                }
                            });
                        })

                        oLayer.clearLayers();
                        oLayer.addData(aGeoJson);
                        {$autoZoomJs}

")}
                };

                {$this->buildJsLeafletVar()}.on('exfRefresh', oLayer._exfRefresh);
                oLayer._exfRefresh();
               
                return oLayer;
            })()
JS;
    }
    
    protected abstract function buildJsLeafletDataLoader(string $oRequestParamsJs, string $aResultRowsJs, string $onLoadedJs) : string;
    
    protected function buildJsAutoZoom(string $oLayerJs) : string
    {
        return <<<JS

                    setTimeout(function() {
                        var oBounds = $oLayerJs.getBounds();
                        if (oBounds !== undefined && oBounds.isValid()) {
                            {$this->buildJsLeafletVar()}.fitBounds(oBounds);
                        }
                	},100);

JS;
    }
    
    protected function buildJsMarkerIcon(DataMarkersLayer $layer, string $oRowJs) : string
    {
        $icon = $layer->getIcon() ?? '';
        $prefix = $layer->getIconSet() ?? 'fa';
        
        if ($layer->hasValue()) {
            $color = $layer->getColor() ?? $this->getLayerColors()[$this->getWidget()->getLayerIndex($layer)];
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
    
    public function getLayerBaseColor(MapLayerInterface $layer) : string
    {
        return $this->getLayerColors()[$this->getWidget()->getLayerIndex($layer)];
    }
    
    protected function getLayerColors() : array
    {
        return [
            'blue',
            'violet', 
            'darkslategray', 
            'indigo',
            'darkgreen', 
            'black', 
            'gold',
            'orange',
            'seagreen'
        ];
    }
    
    public function buildJsLeafletVar() : string
    {
        return 'leaflet_' . $this->getId();
    }
    
    public function getIdLeaflet() : string
    {
        return $this->getId();
    }
    
    public function buildCssElementClass()
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
    
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null) : string
    {
        $widget = $this->getWidget();
        $rows = '';
        if (is_null($action)) {
            $rows = "{$this->buildJsLeafletVar()}._exfState.selectedFeature ? {$this->buildJsLeafletVar()}._exfState.selectedFeature.properties.data : []";
        } elseif ($action instanceof iReadData) {
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            return $this->getFacade()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter($action);
        } else {
            $rows = $this->buildJsLeafletGetSelectedRows();
        }
        return "{oId: {$this->buildJsLeafletVar()}._exfState.selectedFeature ? {$this->buildJsLeafletVar()}._exfState.selectedFeature.properties.object : '{$widget->getMetaObject()->getId()}', rows: $rows}";
    }
    
    protected function buildJsLeafletGetSelectedRows() : string
    {
        return "{$this->buildJsLeafletVar()}._exfState.selectedFeature ? [{$this->buildJsLeafletVar()}._exfState.selectedFeature.properties.data] : []";
    }
    
    /**
     * build function to get value of a selected data row
     *
     * @param string $column
     * @param int $row
     * @throws FacadeOutputError
     * @return string
     */
    public function buildJsValueGetter($column = null, $row = null) : string
    {
        if ($column != null) {
            $key = $column;
        } else {
            if ($this->getWidget()->getData()->hasUidColumn() === true) {
                $column = $this->getWidget()->getData()->getUidColumn();
                $key = $column->getDataColumnName();
            } else {
                throw new FacadeOutputError('Cannot create a value getter for a data widget without a UID column: either specify a column to get the value from or a UID column for the table.');
            }
        }
        if ($row != null) {
            throw new FacadeOutputError('Unsupported function ');
        }
        
        return <<<JS
function(){
                    var aSelected = {$this->buildJsLeafletGetSelectedRows()};
                    return aSelected.map(function(oRow){
                        return oRow['$key'];
                    }).join(',');
                }()
                
JS;
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsLeafletRefresh() : string
    {
        return "{$this->buildJsLeafletVar()}.fire('exfRefresh')";
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsLeafletResize() : string
    {
        return "{$this->buildJsLeafletVar()}.invalidateSize()";
    }
}