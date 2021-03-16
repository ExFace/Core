<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Parts\Maps\DataMarkersLayer;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Widgets\Parts\Maps\Interfaces\MarkerMapLayerInterface;
use exface\Core\Interfaces\Widgets\iUseData;

/**
 * This trait helps render Map widgets with Leaflet JS.
 * 
 * ## How to use: 
 * 
 * 1. Add the following dependencies to the composer.json of the facade: 
 *      ```
 *      "npm-asset/leaflet" : "^1.7",
 *     	"npm-asset/leaflet-extra-markers" : "^1.2",
 *     	"npm-asset/leaflet-fullscreen" : "^1.0",
 *     	"npm-asset/leaflet.locatecontrol" : "~0.72",
 *     	"npm-asset/esri-leaflet" : "^3.0",
 *     	"npm-asset/leaflet.markercluster" : "^1.4"
 *      ```
 * 2. Add the config options to the facade:
 *      ```
 *      "LIBS.LEAFLET.CSS": "npm-asset/leaflet/dist/leaflet.css",
 *  	"LIBS.LEAFLET.JS": "npm-asset/leaflet/dist/leaflet.js",
 *  	"LIBS.LEAFLET.EXTRA_MARKERS_CSS": "npm-asset/leaflet-extra-markers/dist/css/leaflet.extra-markers.min.css",
 *  	"LIBS.LEAFLET.EXTRA_MARKERS_JS": "npm-asset/leaflet-extra-markers/dist/js/leaflet.extra-markers.min.js",
 *  	"LIBS.LEAFLET.MARKERCLUSTER_CSS": "npm-asset/leaflet.markercluster/dist/MarkerCluster.css",
 *  	"LIBS.LEAFLET.MARKERCLUSTER_JS": "npm-asset/leaflet.markercluster/dist/leaflet.markercluster.js",
 *  	"LIBS.LEAFLET.FULLSCREEN_CSS": "npm-asset/leaflet-fullscreen/dist/Leaflet.fullscreen.css",
 *  	"LIBS.LEAFLET.FULLSCREEN_JS": "npm-asset/leaflet-fullscreen/dist/Leaflet.fullscreen.min.js",
 *  	"LIBS.LEAFLET.LOCATECONTROL_CSS": "npm-asset/leaflet.locatecontrol/dist/L.Control.Locate.min.css",
 *  	"LIBS.LEAFLET.LOCATECONTROL_JS": "npm-asset/leaflet.locatecontrol/dist/L.Control.Locate.min.js",
 *  	"LIBS.LEAFLET.ESRI.JS": "npm-asset/esri-leaflet/dist/esri-leaflet.js",
 *      ```
 * 3. Use the trait in your element by creating a globally accessible variable or 
 * property `buildJsLeafletVar()` and calling `buildJsLeafletInit()` at a time, 
 * where the map `div` is available and the map is to be rendered. This method will
 * initialize the leaflet variable.
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
    
    protected function getZoomInitial() : int
    {
        return $this->getWidget()->getZoom() ?? 2;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsLeafletInit() : string
    {
        $widget = $this->getWidget();
        
        $zoom = $this->getZoomInitial();
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
        selectedFeature: null,
        initialZoom: {$this->getZoomInitial()}
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
        $baseMapsJs = '';
        $baseSelected = $this->getWidget()->getBaseMap(0);
        foreach ($this->getWidget()->getBaseMaps() as $idx => $layer) {
            $captionJs = json_encode($layer->getCaption() ?? 'Map ' . ($idx+1));
            $visible = ($baseSelected === $layer);
            $layerInit = $this->buildJsLayer($layer);
            if ($layerInit) {
                $baseMapsJs .= "{$captionJs} : {$layerInit}";
                if ($visible) {
                    $baseMapsJs .= ".addTo({$this->buildJsLeafletVar()})";
                }
                $baseMapsJs .= ',';
            }
        }
        
        $featureLayersJs = '';
        foreach ($this->getWidget()->getLayers() as $index => $layer) {
            $layerInit = $this->buildJsLayer($layer);
            if ($layerInit) {
                $captionJs = json_encode($layer->getCaption());
                $visible = true;
                $autoZoom = $layer->getAutoZoomToSeeAll() === true ? 'true' : 'false';
                
                if ($visible) {
                    $layerInit .= ".addTo({$this->buildJsLeafletVar()})";
                }
                
                $optionsJs = '';
                if ($layer instanceof iUseData) {
                    $optionsJs .= "oId : '{$layer->getMetaObject()->getId()}',";
                }
                    
                $featureLayersJs .= <<<JS

        {
            index: $index,
            caption: $captionJs,
            autoZoom: {$autoZoom},
            $optionsJs
            layer: $layerInit
        },
JS;
            }
        }
        
        return <<<JS

    var oBaseMapsList = {
        $baseMapsJs
    };

    var aLayers = [
        $featureLayersJs
    ];
    var oLayerList = {};

    aLayers.forEach(function(oLayerData){
        oLayerList[oLayerData.caption] = oLayerData.layer;
    });
    
    L.control.layers(oBaseMapsList, oLayerList)
    .addTo({$this->buildJsLeafletVar()});

    {$this->buildJsLeafletVar()}._exfLayers = aLayers;

JS;
    }
    
    protected function buildJsLayerGetter(MapLayerInterface $layer) : string
    {
        return "{$this->buildJsLeafletVar()}._exfLayers.find(function(oLayerData){oLayerData.index === {$this->getWidget()->getLayerIndex($layer)}})";
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
                                    sHtml += '<tr class="' + (oRow.class || '') + '" title="' + (oRow.tooltip || '') + '"><td>' + oRow.caption + ':</td><td>' + (oRow.value === null || oRow.value === undefined ? '' : oRow.value) + '</td></tr>';
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
                $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
                $popupTableRowsJs .= "{
                    class: \"exf-{$visibility}\", 
                    tooltip: $hint, 
                    caption: $caption, 
                    value: {$formatter->buildJsFormatter("feature.properties.data['{$col->getDataColumnName()}']")} },";
            }
        }
        
        $showPopupJs = $this->buildJsLeafletPopup($popupCaptionJs, $this->buildJsLeafletPopupList("[$popupTableRowsJs]"), 'layer');
                     
        if ($layer->getAutoZoomToSeeAll() === true || $layer->getAutoZoomToSeeAll() === null && count($this->getWidget()->getDataLayers()) === 1){
            $autoZoomJs = $this->buildJsAutoZoom('oLayer');
        }
        
        if ($layer->isClusteringMarkers() !== false) {
            $clusterInitJs = <<<JS
L.markerClusterGroup({
                    iconCreateFunction: {$this->buildJsClusterIcon($layer, 'cluster')},
                })
JS;
        } else {
            $clusterInitJs = 'null';
        }
        
        if ($link = $layer->getDataWidgetLink()) {
            $linkedEl = $this->getFacade()->getElement($link->getTargetWidget());
            $exfRefreshJs = <<<JS
function() {
                    setTimeout(function(){
                        var oData = {$linkedEl->buildJsDataGetter()};
                        var aRows = oData.rows || []; 
                        var aGeoJson = [];
                        var aRowsSkipped = [];
                        
                        {$this->buildJsConvertDataRowsToGeoJSON($layer, 'aRows', 'aGeoJson', 'aRowsSkipped')}
                        
                        oLayer.clearLayers();
                        oLayer.addData(aGeoJson);
                        {$autoZoomJs}
                        
                        if (oClusterLayer !== null) {
                            oClusterLayer.clearLayers().addLayer(oLayer);
                        }
                    }, 100);
                }
                
JS;
        } else {
            $exfRefreshJs = <<<JS
function() {
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
                        
                        {$this->buildJsConvertDataRowsToGeoJSON($layer, 'aRows', 'aGeoJson', 'aRowsSkipped')}
                        
                        oLayer.clearLayers();
                        oLayer.addData(aGeoJson);
                        {$autoZoomJs}
                        
                        if (oClusterLayer !== null) {
                            oClusterLayer.clearLayers().addLayer(oLayer);
                        }
                        
")}
                }
JS;
        }
        
        return <<<JS
            (function(){
                var oLeaflet = {$this->buildJsLeafletVar()};
                var oClusterLayer = {$clusterInitJs};
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

                oLayer._exfRefresh = $exfRefreshJs;

                oLeaflet.on('exfRefresh', oLayer._exfRefresh);
                oLayer._exfRefresh();
               
                return oClusterLayer ? oClusterLayer : oLayer;
            })()
JS;
    }
    
    /**
     * 
     * @param iUseData $layer
     * @param string $aRowsJs
     * @param string $aGeoJsonJs
     * @param string $aRowsSkippedJs
     * @return string
     */
    protected function buildJsConvertDataRowsToGeoJSON(iUseData $layer, string $aRowsJs, string $aGeoJsonJs, string $aRowsSkippedJs) : string
    {
        return <<<JS

                        $aRowsJs.forEach(function(oRow){
                            var fLat = parseFloat(oRow.{$layer->getLatitudeColumn()->getDataColumnName()});
                            var fLng = parseFloat(oRow.{$layer->getLongitudeColumn()->getDataColumnName()});
        
                            if (isNaN(fLat) || isNaN(fLng)) {
                                $aRowsSkippedJs.push(oRow);
                                return;
                            }
    
                            $aGeoJsonJs.push({
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

JS;
    }
    
    protected abstract function buildJsLeafletDataLoader(string $oRequestParamsJs, string $aResultRowsJs, string $onLoadedJs) : string;
    
    /**
     * Returns the JS code to pan/zoom the map after the data of a layer is refreshed
     * 
     * @param string $oLayerJs
     * @return string
     */
    public function buildJsAutoZoom(string $oLayerJs) : string
    {
        return <<<JS

                    setTimeout(function() {
                        var oBounds = $oLayerJs.getBounds();
                        var oMap = {$this->buildJsLeafletVar()};
                        if (oBounds !== undefined && oBounds.isValid()) {
                            if (oMap.getBoundsZoom(oBounds) < oMap.getZoom() || oMap.getZoom() === oMap._exfState.initialZoom) {
                                {$this->buildJsLeafletVar()}.fitBounds(oBounds, {padding: [10,10]});
                            }
                        }
                	},100);

JS;
    }
    
    protected function buildJsMarkerIcon(DataMarkersLayer $layer, string $oRowJs) : string
    {
        $icon = $layer->getIcon() ?? 'fa-map-marker';
        $prefix = $layer->getIconSet() ?? 'fa';
        $color = $layer->getColor() ?? $this->getLayerColors()[$this->getWidget()->getLayerIndex($layer)];
        
        if ($layer->hasValue()) {
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
            return <<<JS
new L.ExtraMarkers.icon({
                            icon: '$icon',
                            extraClasses: 'fa-5x',
                            markerColor: '$color',
                            prefix: '$prefix',
                            svg: true,
                        })
                        
JS;
        }
    }
    
    protected function buildJsClusterIcon(DataMarkersLayer $layer, string $oClusterJs) : string
    {
        $color = $layer->getColor() ?? $this->getLayerColors()[$this->getWidget()->getLayerIndex($layer)];
        $caption = str_replace("'", "\\'", trim(json_encode($layer->getCaption()), '"'));
        
        /* TODO SUM values instead of counting if needed
         var markers = oCluster.getAllChildMarkers();
         var n = 0;
         for (var i = 0; i < markers.length; i++) {
         n += 1;
         */
        $sContentJs = "var sContent = '(' + iCnt + ')';";
        
        return <<<JS
function ($oClusterJs) {
                        var oCluster = $oClusterJs;
                        var iCnt = oCluster.getChildCount();
                        {$sContentJs}
	                    return new L.DivIcon({
                            html: '<div title="' + iCnt + 'x {$caption}" style="background-color: {$color}; box-shadow: 0 0 10px 5px {$color}"><i>' + sContent + '</i></div>',
                            className: 'marker-cluster',
                            iconSize: new L.Point(40, 40)
                        });
        			}
        			
JS;
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
        
        foreach ($this->getWidget()->getLayers() as $layer) {
            if (($layer instanceof MarkerMapLayerInterface) && $layer->isClusteringMarkers() !== false) {
                $includes[] = '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.MARKERCLUSTER_CSS') . '"/>';
                $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.MARKERCLUSTER_JS') . '"></script>';
                break;
            }
        }
        
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
    
    /**
     *
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait
     */
    protected abstract function registerLiveReferenceAtLinkedElements();
}