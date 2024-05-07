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
use exface\Core\Widgets\Parts\Maps\DataSelectionMarkerLayer;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\SaveData;
use exface\Core\Widgets\Map;
use exface\Core\Exceptions\Facades\FacadeUnsupportedWidgetPropertyWarning;
use exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngDataColumnMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\LatLngWidgetLinkMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\EditableMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\DataPointsLayer;
use exface\Core\Widgets\Parts\Maps\Interfaces\ColoredDataMapLayerInterface;
use exface\Core\Interfaces\Widgets\iHaveColor;
use exface\Core\Widgets\Parts\Maps\DataLinesLayer;
use exface\Core\Widgets\Image;
use exface\Core\Widgets\DataButton;
use exface\Core\Widgets\Parts\Maps\DataShapesLayer;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\CustomProjectionMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Projection\Proj4Projection;
use exface\Core\Widgets\Parts\Maps\Interfaces\ValueLabeledMapLayerInterface;
use exface\Core\Interfaces\Widgets\iCanBeDragAndDropTarget;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonWidgetLinkMapLayerInterface;

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
 *     	"npm-asset/leaflet.markercluster" : "^1.4",
 *      "npm-asset/proj4leaflet": "^1"
 *     
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
 *  	"LIBS.LEAFLET.PROJ4.PROJ4JS": "npm-asset/proj4/dist/proj4.js",
 *  	"LIBS.LEAFLET.PROJ4.PROJ4LEAFLETJS": "npm-asset/proj4leaflet/src/proj4leaflet.js",
 *  
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
    use JsValueScaleTrait;
    
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
        $this->addLeafletLayerRenderer([$this, 'buildJsLayerDataMarkers']);
        $this->addLeafletLayerRenderer([$this, 'buildJsLayerDataPaths']);
        $this->addLeafletLayerRenderer([$this, 'buildJsLayerDataGeoJson']);
        return;
    }
    
    /**
     * 
     * @param WidgetInterface $widget
     */
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

        $popupJs = $this->getWidget()->getShowPopupOnClick() ?  $this->buildJsLeafletPopup('"Location info"', $this->buildJsLeafletPopupList("[
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
        ]"), "[latlng.lat,latlng.lng]") : '';

        
        return <<<JS

(function(){
    var oMap;
    oMap = {$this->buildJsLeafletVar()} = L.map('{$this->getIdLeaflet()}', {
        {$this->buildJsMapOptions()}
    })
    .setView([{$lat}, {$lon}], {$zoom})
    .on('contextmenu', function(e) {
        var latlng = e.latlng;
        var layer = e.target;
       
        {$popupJs} 
    });
    $('#{$this->getIdLeaflet()}').data('_exfLeaflet', {$this->buildJsLeafletVar()});

    {$this->buildJsLeafletVar()}._exfState = {
        selectedFeature: null,
        initialZoom: {$this->getZoomInitial()}
    };
    {$this->buildJsLeafletVar()}._exfLayers = {};

    {$this->buildJsLeafletControlLocate()}
    {$this->buildJsLeafletControlScale()}
    {$this->buildJsMapDrop()}
    {$this->buildJsLayers()}
    {$this->buildJsLeafletDrawInit('oMap')}
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
        
        if (Map::COORDINATE_SYSTEM_AUTO !== $crs = $widget->getCoordinateSystem()) {
            switch ($crs) {
                case Map::COORDINATE_SYSTEM_PIXELS:
                    $mapOptions .= "
        crs: L.CRS.Simple,";
                    break;
                default:
                    throw new FacadeUnsupportedWidgetPropertyWarning('Map coordinate system "' . $crs . '" currently not supported in this facade!');
            }
        }
        
        if (null !== $val = $widget->getZoomMin()) {
            $mapOptions .= "
        minZoom: {$val},";
        }
        if (null !== $val = $widget->getZoomMax()) {
            $mapOptions .= "
        maxZoom: {$val},";
        }

        if (!$widget->getDoubleClickToZoom()) {
            $mapOptions .= "
        doubleClickZoom: false,";
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
        foreach ($this->getWidget()->getBaseMaps() as $layer) {
            $captionJs = $this->escapeString($layer->getCaption(), true, false);
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
                $captionJs = $this->escapeString($layer->getCaption(), true, false);
                $visible = $layer->getVisibility() >= WidgetVisibilityDataType::NORMAL;
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
            visibility: {$layer->getVisibility()},
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
    {$this->buildJsLeafletVar()}._exfBaseMaps = oBaseMapsList;

JS;
    }
    
    public function buildJsLayerGetter(MapLayerInterface $layer, string $leafletVarJs = null) : string
    {
        if ($leafletVarJs === null) {
            $leafletVarJs = $this->buildJsLeafletVar();
        }
        return "{$leafletVarJs}._exfLayers.find(function(oLayerData){return oLayerData.index === {$this->getWidget()->getLayerIndex($layer)}})";
    }
    
    public function buildJsBaseMapGetter(BaseMapInterface $layer, string $leafletVarJs = null) : string
    {
        if ($leafletVarJs === null) {
            $leafletVarJs = $this->buildJsLeafletVar();
        }
        $caption = $this->escapeString($layer->getCaption(), true, false);
        return "{$leafletVarJs}._exfBaseMaps[{$caption}]";
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
        //close button in the popup is hidden as it actually is a link to the root page url with '#close' attached to it
        //this is not customizable in the options of the popup, so we hide it to prevent jumping out
        //of the dialog with the actual leaflet
        return <<<JS

                        (function() {
                            var sTitle = $titleJs;
                            var sContent = (sTitle ? '<h3>' + $titleJs + '</h3>' : '') + $contentJs;
                            if (Array.isArray($bindToJs)){
                                L.popup({
                                    className: "exf-map-popup",
                                    closeButton: false
                                })
                                    .setLatLng($bindToJs)
                                    .setContent(sContent)
                                    .openOn({$this->buildJsLeafletVar()});
                            } else {
                                $bindToJs.bindPopup(sContent, {
                                    className: "exf-map-popup",
                                    closeButton: false
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
    
    protected function buildJsLayerDataPaths(MapLayerInterface $layer) : ?string
    {
        if (! ($layer instanceof DataLinesLayer)) {
            return null;
        }
        
        /* @var $dataWidget \exface\Core\Widgets\Data */
        $dataWidget = $layer->getDataWidget();
        
        // Render popup (speech bubble) with a list of data row values
        $popupTableRowsJs = '';
        $popupCaptionJs = $this->escapeString($layer->getCaption(), true, false);
        foreach ($dataWidget->getColumns() as $col) {
            if ($col->isHidden() === true) {
                continue;
            }
            if ($col->getCellWidget() instanceof Image) {
                continue;
            }
            $visibility = strtolower(WidgetVisibilityDataType::findKey($col->getVisibility()));
            $hint = $this->escapeString($col->getHint() ?? '', true, false);
            $caption = $this->escapeString($col->getCaption() ?? '', true, false);
            $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
            $popupTableRowsJs .= "{
                class: \"exf-{$visibility}\",
                tooltip: $hint,
                caption: $caption,
                value: {$formatter->buildJsFormatter("oLine.properties.data['{$col->getDataColumnName()}']")} },";
            
        }
        
        $showPopupJs = $this->buildJsLeafletPopup($popupCaptionJs, $this->buildJsLeafletPopupList("[$popupTableRowsJs]"), 'oLine');
        
        // Add auto-zoom
        if ($layer->getAutoZoomToSeeAll() === true || $layer->getAutoZoomToSeeAll() === null && count($this->getWidget()->getDataLayers()) === 1){
            $autoZoomJs = $this->buildJsAutoZoom('oLayer', $layer->getAutoZoomMax());
        }
        $color = $layer->getColor() ? $layer->getColor() : $this->getLayerColors()[$this->getWidget()->getLayerIndex($layer)];
        // Generate JS to run on map refresh
        switch (true) {
            case $link = $layer->getDataWidgetLink():
                $linkedEl = $this->getFacade()->getElement($link->getTargetWidget());
                if ($layer instanceof DataSelectionMarkerLayer) {
                    $asIfForAction = ActionFactory::createFromString($layer->getWorkbench(), SaveData::class);
                } else {
                    $asIfForAction = null;
                }
                $exfRefreshJs = <<<JS
function() {
                    var oData = {$linkedEl->buildJsDataGetter($asIfForAction)};
                    var aRows = oData.rows || [];
                    var aRowsSkipped = [];
                    
                    oLayer.clearLayers();
                    aRows.forEach(function(oRow) {
                        var fLatFrom = oRow['{$layer->getFromLatitudeColumn()->getDataColumnName()}'];
                        var fLatTo = oRow['{$layer->getToLatitudeColumn()->getDataColumnName()}'];
                        var fLngFrom = oRow['{$layer->getFromLongitudeColumn()->getDataColumnName()}'];
                        var fLngTo = oRow['{$layer->getToLongitudeColumn()->getDataColumnName()}'];
                        var oLine;

                        switch (true) {
                            case fLatFrom === null || fLatFrom === undefined:
                            case fLatTo === null || fLatTo === undefined:
                            case fLngFrom === null || fLngFrom === undefined:
                            case fLngTo === null || fLngTo === undefined:
                                aRowsSkipped.push(oRow);
                                return;
                        }

                        oLine = L.polyline([
                            [fLatFrom, fLngFrom],
                            [fLatTo, fLngTo]
                        ], {
                            color: '{$color}',
                            weight: {$layer->getWidth()}
                        });
                        
                        oLine.properties = {
                            layer: {$this->getWidget()->getLayerIndex($layer)},
                            object: '{$layer->getMetaObject()->getId()}',
                            data: oRow,
                        };

                        $showPopupJs
                        oLayer.addLayer(oLine);
                    });
                    
                    {$autoZoomJs}
                }
                
JS;
                    break;
            default:
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
                        
                        var aRowsSkipped = [];
                        
                        oLayer.clearLayers();
                        aRows.forEach(function(oRow) {
                            var fLatFrom = oRow['{$layer->getFromLatitudeColumn()->getDataColumnName()}'];
                            var fLatTo = oRow['{$layer->getToLatitudeColumn()->getDataColumnName()}'];
                            var fLngFrom = oRow['{$layer->getFromLongitudeColumn()->getDataColumnName()}'];
                            var fLngTo = oRow['{$layer->getToLongitudeColumn()->getDataColumnName()}'];
                            var oLine;

                            switch (true) {
                                case fLatFrom === null || fLatFrom === undefined:
                                case fLatTo === null || fLatTo === undefined:
                                case fLngFrom === null || fLngFrom === undefined:
                                case fLngTo === null || fLngTo === undefined:
                                    aRowsSkipped.push(oRow);
                                    return;
                            }

                            oLine = L.polyline([
                                [fLatFrom, fLngFrom],
                                [fLatTo, fLngTo]
                            ], {
                                color: '{$color}',
                                weight: {$layer->getWidth()}
                            });

                            oLine.properties = {
                                layer: {$this->getWidget()->getLayerIndex($layer)},
                                object: '{$layer->getMetaObject()->getId()}',
                                data: oRow,
                            };

                            $showPopupJs
                            oLayer.addLayer(oLine);
                        });
                        
                        {$autoZoomJs}
                        
                    ")}
                }
JS;
        }
        
        return <<<JS
            (function(){
                var oLeaflet = {$this->buildJsLeafletVar()};
                var oLayer = L.featureGroup();
                
                oLayer._exfRefresh = $exfRefreshJs;
                
                oLeaflet.on('exfRefresh', oLayer._exfRefresh);
                oLayer._exfRefresh();
                
                return oLayer;
            })()
JS;
    }
    
    /**
     * 
     * @param MapLayerInterface $layer
     * @return string
     */
    protected function buildJsLayerDataMarkers(MapLayerInterface $layer) : ?string
    {
        if (! ($layer instanceof LatLngDataColumnMapLayerInterface)) {
            return null;
        }
        
        /* @var $dataWidget \exface\Core\Widgets\Data */
        $dataWidget = $layer->getDataWidget();
        
        // Render popup (speech bubble) with a list of data row values
        $popupTableRowsJs = '';
        $popupCaptionJs = $this->escapeString($layer->getCaption(), true, false);
        foreach ($dataWidget->getColumns() as $col) {
            if ($col->isHidden() === true) {
                continue;
            }
            if ($col->getCellWidget() instanceof Image) {
                continue;
            }
            $visibility = strtolower(WidgetVisibilityDataType::findKey($col->getVisibility()));
            $hint = $this->escapeString($col->getHint() ?? '', true, false);
            $caption = $this->escapeString($col->getCaption() ?? '', true, false);
            $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
            $popupTableRowsJs .= "{
                class: \"exf-{$visibility}\", 
                tooltip: $hint, 
                caption: $caption, 
                value: {$formatter->buildJsFormatter("feature.properties?.data['{$col->getDataColumnName()}']")} },";
        }
        
        $showPopupJs = $this->buildJsLeafletPopup($popupCaptionJs, $this->buildJsLeafletPopupList("[$popupTableRowsJs]"), 'layer');
          
        // Add auto-zoom
        if ($layer->getAutoZoomToSeeAll() === true || $layer->getAutoZoomToSeeAll() === null && count($this->getWidget()->getDataLayers()) === 1){
            $autoZoomJs = $this->buildJsAutoZoom('oLayer', $layer->getAutoZoomMax());
        }
        
        // Add clustering
        if (($layer instanceof MarkerMapLayerInterface) && $layer->isClusteringMarkers() !== false) {
            $clusterInitJs = <<<JS
L.markerClusterGroup({
                    iconCreateFunction: {$this->buildJsClusterIcon($layer, 'cluster')},
                })
JS;
        } else {
            $clusterInitJs = 'null';
        }
        
        // Generate JS to run on map refresh
        switch (true) {
            case ($layer instanceof LatLngWidgetLinkMapLayerInterface) && ($latLink = $layer->getLatitudeWidgetLink()) && ($lngLink = $layer->getLongitudeWidgetLink()):
                $latEl = $this->getFacade()->getElement($latLink->getTargetWidget());
                $lngEl = $this->getFacade()->getElement($lngLink->getTargetWidget());
                $exfRefreshJs = <<<JS
function() {
                    var aRows = [{
                        {$latEl->getWidget()->getDataColumnName()}: {$latEl->buildJsValueGetter()},
                        {$lngEl->getWidget()->getDataColumnName()}: {$lngEl->buildJsValueGetter()}
                    }];
                    var aGeoJson = [];
                    var aRowsSkipped = [];
                    
                    {$this->buildJsConvertDataRowsToGeoJSON($layer, 'aRows', 'aGeoJson', 'aRowsSkipped')}
                    
                    oLayer.clearLayers();
                    oLayer.addData(aGeoJson);
                    {$autoZoomJs}
                    
                    if (oClusterLayer !== null) {
                        oClusterLayer.clearLayers().addLayer(oLayer);
                    }
                }
                
JS;
                
                break;
            case $link = $layer->getDataWidgetLink():
                $linkedEl = $this->getFacade()->getElement($link->getTargetWidget());
                if ($layer instanceof DataSelectionMarkerLayer) {
                    $asIfForAction = ActionFactory::createFromString($layer->getWorkbench(), SaveData::class);
                } else {
                    $asIfForAction = null;
                }
                $exfRefreshJs = <<<JS
function() {
                    var oData = {$linkedEl->buildJsDataGetter($asIfForAction)};
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
                }
                
JS;
                break;
            default: 
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
        
        // Set up editable layers
        $initEditingJs = '';
        $updateMarker = '';
        if (($layer instanceof EditableMapLayerInterface) && $layer->isEditable()) {
            if ($layer->hasEditByAddingItems()) {
                if ($latEl && $lngEl) {
                    $updateMarker = $latEl->buildJsValueSetter('fLat') . ';' . $lngEl->buildJsValueSetter('fLng') . ';';
                }
                $maxMarkers = $layer->hasEditByAddingItemsMax() ?? 'null';
                $initEditingJs = <<<JS

                oLeaflet.on('click', function(e){
                    var iMaxMarkers = $maxMarkers;
                    var fLat = e.latlng.lat;
                    var fLng = e.latlng.lng;

                    if (oLayer.getLayers().length >= iMaxMarkers) return;

                    oLayer.addData([
                        {
                            type: 'Feature',
                            geometry: {
                                type: 'Point',
                                coordinates: [fLng, fLat],
                            },
                            properties: {
                                layer: {$this->getWidget()->getLayerIndex($layer)},
                                object: '{$layer->getMetaObject()->getId()}',
                                draggable: true,
                                autoPan: true,
                                data: {}
                            }
                        }
                    ]);
                    $updateMarker
                });

JS;
            }
        }
        
        $markerProps = '';
        if ($layer !== null && $layer->hasTooltip()) {
            $markerProps .= 'title: oRow.' . $layer->getTooltipColumn()->getDataColumnName() . ',';
        }

        return <<<JS
            (function(){
                var oLeaflet = {$this->buildJsLeafletVar()};
                var oClusterLayer = {$clusterInitJs};
                var oLayer = L.geoJSON(null, {
                    pointToLayer: function(feature, latlng) {
                        var bDraggable = feature.properties.draggable || false;
                        var oMarker = L.marker(latlng, { 
                            icon: {$this->buildJsMarkerIcon($layer, 'feature.properties.data')},
                            draggable: bDraggable,
                            autoPan: bDraggable,
                            $markerProps 
                        });
                        if (bDraggable === true) {
                            oMarker.on('dragend', function(e){
                                var fLat = oMarker.getLatLng().lat;
                                var fLng = oMarker.getLatLng().lng;
                                $updateMarker
                            });
                        }
                        return oMarker;
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
                            {$this->getOnChangeScript()}
                        });
                    
                    }
                });

                $initEditingJs

                oLayer._exfRefresh = $exfRefreshJs;

                oLeaflet.on('exfRefresh', oLayer._exfRefresh);
                oLayer._exfRefresh();
               
                return oClusterLayer ? oClusterLayer : oLayer;
            })()
JS;
    }
    
    /**
     * 
     * @param MapLayerInterface $layer
     * @param AjaxFacadeElementInterface $facadeElement
     * @return string|NULL
     */
    protected function buildJsLayerDataGeoJson(MapLayerInterface $layer, AjaxFacadeElementInterface $facadeElement) : ?string
    {
        if (! ($layer instanceof GeoJsonMapLayerInterface) || ! ($layer instanceof iUseData)) {
            return null;
        }
        
        /* @var $dataWidget \exface\Core\Widgets\Data */
        $dataWidget = $layer->getDataWidget();
        
        // Render popup (speech bubble) with a list of data row values
        $popupTableRowsJs = '';
        $popupCaptionJs = $this->escapeString($layer->getCaption(), true, false);
        foreach ($dataWidget->getColumns() as $col) {
            if ($col->isHidden() === true) {
                continue;
            }
            if ($col->getCellWidget() instanceof Image) {
                continue;
            }
            $visibility = strtolower(WidgetVisibilityDataType::findKey($col->getVisibility()));
            $hint = $this->escapeString($col->getHint() ?? '', true, false);
            $caption = $this->escapeString($col->getCaption() ?? '', true, false);
            $formatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
            $popupTableRowsJs .= "{
                class: \"exf-{$visibility}\",
                tooltip: $hint,
                caption: $caption,
                value: {$formatter->buildJsFormatter("feature.properties?.data['{$col->getDataColumnName()}']")} },";
        }
        
        $showPopupJs = $layer->getShowPopupOnClick() ? $this->buildJsLeafletPopup($popupCaptionJs, $this->buildJsLeafletPopupList("[$popupTableRowsJs]"), 'layer') : '';
        
        // Add auto-zoom
        if ($layer->getAutoZoomToSeeAll() === true || $layer->getAutoZoomToSeeAll() === null && count($this->getWidget()->getDataLayers()) === 1){
            $autoZoomJs = $this->buildJsAutoZoom('oLayer', $layer->getAutoZoomMax());
        }
        
        // Add styling and colors
        $color = $this->buildJsLayerColor($layer, 'feature.properties.data');
        if (null !== $weight = $layer->getLineWeight()) {
            $styleJs .= "weight: $weight,";
        }
        if (null !== $opacity = $layer->getOpacity()) {
            $styleJs .= "opacity: $opacity,";
        }
        if ($color !== '' && $color !== "''") {
            $styleJs .= "color: $color,";
        }
        
        // Define a custom projection if needed
        if (($layer instanceof CustomProjectionMapLayerInterface) && $layer->hasProjectionDefinition() && $layer->getProjection() instanceof Proj4Projection) {
            $proj = $layer->getProjection();
            $projectionInit = "proj4.defs('{$proj->getName()}', '{$proj->getDefinition()}');";
            $layerConstructor = 'L.Proj.geoJson';
        } else {
            $layerConstructor = 'L.geoJSON';
        }
        
        $drawInitJs = '';
        if (($layer instanceof EditableMapLayerInterface) && $layer->isEditable()) {
            if (($layer instanceof GeoJsonWidgetLinkMapLayerInterface) && $link = $layer->getShapesWidgetLink()) {
                $onDrawJs = $this->getFacade()->getElement($link->getTargetWidget())->buildJsValueSetter('JSON.stringify(oFeature.geometry)');
            }
            $drawInitJs = <<<JS

            // Callback when a shape is created
            oMap.on('pm:create', function (e) {
                // Delete old shape when new shape is created
                var oParentLayer = {$this->buildJsLayerGetter($layer, 'oMap')}.layer;
                var oFeature = e.layer.toGeoJSON();
                oFeature.properties.data = {};
                oFeature.properties.draggable = true;
                {$onDrawJs}
                e.layer.remove();
                oParentLayer.clearLayers();
                oParentLayer.addData(oFeature);
            });


            // When user presses on drag mode
            oMap.on("pm:globaldragmodetoggled", function (e) {
                if (!e.enabled) return;
                oMap.eachLayer(item => {
                    if (item?.feature?.properties?.draggable && item?.pm) {
                        // Save the new coords of adjusted shape in model
                        item.on('pm:dragdisable', function (updatedEvent) {
                            var oFeature = updatedEvent.layer.toGeoJSON();
                            oFeature.properties.data = {};
                            oFeature.properties.draggable = true;
                            {$onDrawJs}
                        });
                    }

                    // disable drag function for non-editable shapes
                    if (item?.feature && !item?.feature?.properties?.draggable && item?.pm?.disableLayerDrag) {
                        item?.pm?.disableLayerDrag();
                    }
                })
            });

            // When user presses on edit mode
            oMap.on("pm:globaleditmodetoggled", function (e) {
                if (!e.enabled) return;
                oMap.eachLayer(item => {
                    // enable callback for edit action
                    if (item?.feature?.properties?.draggable && item?.pm) {
                        item.on('pm:update', function (updatedEvent) {
                            var oFeature = updatedEvent.layer.toGeoJSON();
                            oFeature.properties.data = {};
                            oFeature.properties.draggable = true;
                            {$onDrawJs}
                        });
                    }
                    // disable edit function for non-editable shapes
                    if (item?.feature && !item?.feature?.properties?.draggable && item?.pm?.disable) {
                        item?.pm?.disable();
                    }
                })
            });

            // When user presses on rotate mode
            oMap.on("pm:globalrotatemodetoggled", function (e) {
                if (!e.enabled) return;
                oMap.eachLayer(item => {
                    // enable callback for rotate action
                    if (item?.feature?.properties?.draggable && item?.pm) {
                        item.on('pm:rotatedisable', function (updatedEvent) {
                            var oFeature = updatedEvent.layer.toGeoJSON();
                            oFeature.properties.data = {};
                            oFeature.properties.draggable = true;
                            {$onDrawJs}
                        });
                    }
                    if (item?.feature && !item?.feature?.properties?.draggable && item?.pm?.disableRotate) {
                        item?.pm?.disableRotate();
                    }
                })
            });
            
            
JS;
        }
        
        // Generate JS to run on map refresh
        switch (true) {
            case ($layer instanceof GeoJsonWidgetLinkMapLayerInterface) && null !== $link = $layer->getShapesWidgetLink():
                $shapesEl = $this->getFacade()->getElement($link->getTargetWidget());
                $exfRefreshJs = <<<JS

                (function() {
                    var aRows = [{
                        {$shapesEl->getWidget()->getDataColumnName()}: {$shapesEl->buildJsValueGetter()}
                    }];
                    var aGeoJson = [];
                    var aRowsSkipped = [];
                    {$this->buildJsConvertDataRowsToGeoJSON($layer, 'aRows', 'aGeoJson', 'aRowsSkipped')}
                    
                    oLayer.clearLayers();
                    oLayer.addData(aGeoJson);
                    oLayer.bringToFront();
                    {$autoZoomJs}
                })();
                
JS;
                    
                    break;
            case $link = $layer->getDataWidgetLink():
                $linkedEl = $this->getFacade()->getElement($link->getTargetWidget());
                if ($layer instanceof DataSelectionMarkerLayer) {
                    $asIfForAction = ActionFactory::createFromString($layer->getWorkbench(), SaveData::class);
                } else {
                    $asIfForAction = null;
                }
                $exfRefreshJs = <<<JS
                
                (function() {
                    var oData = {$linkedEl->buildJsDataGetter($asIfForAction)};
                    var aRows = oData.rows || [];
                    var aFeatures = [];
                    var aRowsSkipped = [];
                    {$this->buildJsConvertDataRowsToGeoJSON($layer, 'aRows', 'aFeatures', 'aRowsSkipped')}

                    oLayer.clearLayers();
                    oLayer.addData(aFeatures);
                    oLayer.bringToBack();
                    {$autoZoomJs}
                })();
                
JS;
                    break;
            case $layer instanceof iUseData:
                /* @var $dataWidget \exface\Core\Widgets\Data */
                $dataWidget = $layer->getDataWidget();
                
                $exfRefreshJs = <<<JS
                
                (function() {
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
                        
                        var aFeatures = [];
                        var aRowsSkipped = [];
                        
                        {$this->buildJsConvertDataRowsToGeoJSON($layer, 'aRows', 'aFeatures', 'aRowsSkipped')}

                        oLayer.clearLayers();
                        oLayer.addData(aFeatures);
                        oLayer.bringToBack();
                        {$autoZoomJs}
                    ")}
                })();
JS;
        }
        
        if (($layer instanceof ValueLabeledMapLayerInterface) && $layer->hasValue()) {
            if ($layer->getValuePosition() === DataShapesLayer::VALUE_POSITION_TOOLTIP) {
                $layerCaption = '';
                if (! $layer->getHideCaption() && null !== $layerCaption = $layer->getCaption()) {
                    $layerCaptionJs = $this->escapeString($layerCaption . ' ');
                }
                $tooltipJs = <<<JS

                    layer.bindTooltip({$layerCaptionJs} + feature.properties?.data['{$layer->getValueColumn()->getDataColumnName()}'], {
                        permanent: false, 
                        direction: '{$layer->getValuePosition()}',
                        className: 'exf-map-shape-tooltip'
                    }).openTooltip();

JS;
            } else {
                $tooltipJs = <<<JS

                    layer.bindTooltip(feature.properties?.data['{$layer->getValueColumn()->getDataColumnName()}'], {
                        permanent: true, 
                        direction: 'center',
                        className: 'exf-map-shape-title'
                    }).openTooltip();
JS;
            }
        } else {
            $tooltipJs = '';
        }


        $onClickJs = <<<JS
                 (function () {
                        var jqIcon = $(e.target.getElement());
                            if (jqIcon.hasClass('exf-map-shape-selected')) {
                                {$this->buildJsLeafletVar()}._exfState.selectedFeature = null;
                                jqIcon.removeClass('exf-map-shape-selected');
                            } else {
                                $('#{$this->getIdLeaflet()} .leaflet-interactive').removeClass('exf-map-shape-selected');
                                {$this->buildJsLeafletVar()}._exfState.selectedFeature = feature;
                                jqIcon.addClass('exf-map-shape-selected');
                            }
                            {$this->getOnChangeScript()}
                        }())
JS;
            
        return <<<JS
            
        (function(oMap){
            $projectionInit
            $drawInitJs

            var oLayer = $layerConstructor(null, {
                onEachFeature: function (feature, layer) {
                    {$showPopupJs}
                    {$tooltipJs}
                    
                    layer.on('mouseover',function(ev) {
                        var domTarget = ev.target.getElement !== undefined ? ev.target.getElement() : ev.originalEvent.target;
                        $(domTarget).addClass('exf-map-shape-hover');
                    });
                    layer.on('mouseout',function(ev) {
                        var domTarget = ev.target.getElement !== undefined ? ev.target.getElement() : ev.originalEvent.target;
                        $(domTarget).removeClass('exf-map-shape-hover');
                    })

                    var timer = 0;
                    layer.on('click', function (e) {
                        clearTimeout(timer);
                        timer = setTimeout(function() {
                            {$onClickJs}
                        }, 200)
                    });

                    layer.on('dblclick', function (e) {
                        clearTimeout(timer);
                        $('#{$this->getIdLeaflet()} .leaflet-interactive').removeClass('exf-map-shape-selected');
                        {$this->buildJsLeafletVar()}._exfState.selectedFeature = feature;
                        var jqIcon = $(e.target.getElement());
                        jqIcon.addClass('exf-map-shape-selected');    
                        {$this->getOnChangeScript()}
                    });

                    layer.on('mousedown', function (e) {
                        // right click
                        const { originalEvent } = e;
                        if (originalEvent.which === 3 || originalEvent.button === 2)
                        {
                            $('#{$this->getIdLeaflet()} .leaflet-interactive').removeClass('exf-map-shape-selected');
                            {$this->buildJsLeafletVar()}._exfState.selectedFeature = feature;
                            var jqIcon = $(e.target.getElement());
                            jqIcon.addClass('exf-map-shape-selected');    
                            {$this->getOnChangeScript()}
                        }
                    });
                },
                style: function(feature) {
                    var oStyle = { {$styleJs} };
                    return oStyle;
                },
                pointToLayer: function(feature, latlng) {
                    var oProps = feature.properties.data;
                    return L.marker(latlng, {
                        icon: new L.ExtraMarkers.icon({
                            icon: '',
                            markerColor: $color,
                            shape: 'round',
                            prefix: 'fa',
                            svg: true,
                        })
                    });
                },
            });
            
            oLayer._exfRefresh = function() {
                {$exfRefreshJs}
                if (oMap?.pm?.disableDraw) {
                    oMap.pm.disableDraw();
                    oMap.pm.disableGlobalEditMode();
                }
            }
            
            oMap.on('exfRefresh', oLayer._exfRefresh);
            oLayer._exfRefresh();
            
            return oLayer;
        })({$facadeElement->buildJsLeafletVar()})

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
        switch (true) {
            case $layer instanceof DataShapesLayer:
                $shapeColName = $layer->getShapesColumn()->getDataColumnName();
                break;
            case $layer instanceof LatLngWidgetLinkMapLayerInterface && ($linkLat = $layer->getLatitudeWidgetLink()) && ($linkLng = $layer->getLongitudeWidgetLink()):
                $latColName = $linkLat->getTargetWidget()->getDataColumnName();
                $lngColName = $linkLng->getTargetWidget()->getDataColumnName();
                break;
            case $layer instanceof LatLngDataColumnMapLayerInterface:
                $latColName = $layer->getLatitudeColumn()->getDataColumnName();
                $lngColName = $layer->getLongitudeColumn()->getDataColumnName();
                break;
            default:
                throw new FacadeLogicError('Cannot render GeoJSON from map layer "' . get_class($layer) . '"');
        }
        
        if ($latColName !== null && $lngColName !== null) {
            $filteredRowsJs = <<<JS

                            var fLat = parseFloat(oRow.{$latColName});
                            var fLng = parseFloat(oRow.{$lngColName});
        
                            if (isNaN(fLat) || isNaN(fLng)) {
                                $aRowsSkippedJs.push(oRow);
                                return;
                            }
JS;
            $geometryJs = <<<JS
                                {
                                    type: 'Point',
                                    coordinates: [fLng, fLat],
                                }
JS;
        } else {
            $filteredRowsJs = <<<JS
                            
                            var sShape = oRow.{$shapeColName};
                            var oShape;
                            if (sShape === null || sShape === undefined || sShape === '') {
                                $aRowsSkippedJs.push(oRow);
                                return;
                            }
                            try {
                                oShape = JSON.parse(sShape);
                            } catch (e) {
                                $aRowsSkippedJs.push(oRow);
                                console.warn('Cannot parse data as map shape:', e, oRow); 
                            }
                            if (oShape === {}) {
                                $aRowsSkippedJs.push(oRow);
                            }
JS;

            $geometryJs = "oShape";
        }
        
        if (($layer instanceof CustomProjectionMapLayerInterface) && $layer->hasProjectionDefinition()) {
            $projection = <<<JS

                                crs: {
                                    type: 'name',
                                    properties: {
                                        name: '{$layer->getProjection()->getName()}'
                                    }
                                },
JS;
        }
        
        $bDraggableJs = ($layer instanceof EditableMapLayerInterface) && $layer->hasEditByChangingItems() ? 'true' : 'false';
        return <<<JS

                        $aRowsJs.forEach(function(oRow){
                            $filteredRowsJs;
                            $aGeoJsonJs.push({
                                type: 'Feature',
                                geometry: {$geometryJs},
                                properties: {
                                    layer: {$this->getWidget()->getLayerIndex($layer)},
                                    object: '{$layer->getMetaObject()->getId()}',
                                    draggable: {$bDraggableJs},
                                    data: oRow
                                },
                                {$projection}
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
    public function buildJsAutoZoom(string $oLayerJs, int $maxZoom = null) : string
    {
        $maxZoomJs = $maxZoom !== null ? 'maxZoom: ' . $maxZoom . ',' : '';
        return <<<JS

                    setTimeout(function() {
                        var oBounds = $oLayerJs.getBounds();
                        var oMap = {$this->buildJsLeafletVar()};
                        if (oBounds !== undefined && oBounds.isValid()) {
                            if (oMap.getBoundsZoom(oBounds) < oMap.getZoom() || oMap.getZoom() === oMap._exfState.initialZoom) {
                                oMap.fitBounds(oBounds, {padding: [10,10], {$maxZoomJs} });
                            } else if (! oMap.getBounds().contains(oBounds)) {
                                oMap.fitBounds(oBounds, {padding: [10,10], maxZoom: oMap.getZoom() });
                            }
                        }
                	},100);

JS;
    }
    
    /**
     * 
     * @param MapLayerInterface $layer
     * @param string $oRowJs
     * @return string
     */
    protected function buildJsLayerColor(MapLayerInterface $layer, string $oRowJs) : string
    {
        $colorJs = "''";
        $colorCss = '';
        switch (true) {
            case ($layer instanceof ColoredDataMapLayerInterface) && null !== $colorCol = $layer->getColorColumn():
                $semanticColors = $this->getFacade()->getSemanticColors();
                $semanticColorsJs = json_encode(! empty($semanticColors) ? $semanticColors : new \stdClass());
                if ($layer->hasColorScale()) {
                    $colorResolverJs = $this->buildJsScaleResolver('sVal', $layer->getColorScale(), $layer->isColorScaleRangeBased());
                } else {
                    $colorResolverJs = "{$oRowJs}['{$colorCol->getDataColumnName()}']";
                }
                $colorJs = <<<JS
function(){
                                var sVal = {$oRowJs}['{$colorCol->getDataColumnName()}'];
                                var sColor = {$colorResolverJs};
                                var oSemanticColors = $semanticColorsJs;
                                if (oSemanticColors[sColor] !== undefined) {
                                    sColor = oSemanticColors[sColor];
                                }
                                return sColor;
                            }()
JS;
                break;
            case ($layer instanceof iHaveColor) && null !== $colorCss = $layer->getColor():
                $colorJs = "'{$colorCss}'";
                break;
            default:
                $colorCss = $this->getLayerColors()[$this->getWidget()->getLayerIndex($layer)];
                $colorJs = "'{$colorCss}'";
        }
        return $colorJs;
    }
    
    /**
     * 
     * @param LatLngDataColumnMapLayerInterface $layer
     * @param string $oRowJs
     * @return string
     */
    protected function buildJsMarkerIcon(LatLngDataColumnMapLayerInterface $layer, string $oRowJs) : string
    {
        
        $colorJs = $this->buildJsLayerColor($layer, $oRowJs);   
        switch (true) {
            case ($layer instanceof DataPointsLayer):
                $pointSizeCss = $layer->getPointSize() . 'px';
                if ($layer->hasValue()) {
                    $valueJs = "'<div class=\"exf-map-point-value exf-map-point-{$layer->getValuePosition()}\">' + {$oRowJs}['{$layer->getValueColumn()->getDataColumnName()}'] + '</div>'";
                } else {
                    $valueJs = "''";
                }
                $pointJs = "'<div class=\"exf-map-point\" style=\"height: {$pointSizeCss}; width: {$pointSizeCss}; background-color: ' + sColor + '; border-radius: 50%;\"></div>'";
                $js= <<<JS
function(){
                            var sColor = $colorJs;
                            return L.divIcon({
                                className: 'exf-map-point',
                                iconSize: [{$layer->getPointSize()}, {$layer->getPointSize()}],
                                shadowSize: null,
                                html: {$pointJs} + {$valueJs}
                            })
                        }()
JS;
                            break;
            case ($layer instanceof DataMarkersLayer) && $layer->hasValue():
                $js = <<<JS
new L.ExtraMarkers.icon({
                            icon: 'fa-number',
                            number: {$oRowJs}['{$layer->getValueColumn()->getDataColumnName()}'],
                            markerColor: $colorJs,
                            shape: 'square',
                            svg: true,
                        })

JS;
                break;
            case ($layer instanceof DataMarkersLayer):
                $icon = $layer->getIcon() ?? 'fa-map-marker';
                $prefix = $layer->getIconSet() ?? 'fa';
                $js = <<<JS
new L.ExtraMarkers.icon({
                            icon: '$icon',
                            extraClasses: 'fa-5x',
                            markerColor: $colorJs,
                            prefix: '$prefix',
                            svg: true,
                        })
                        
JS;
        }
        return $js;
    }
    
    protected function buildJsClusterIcon(DataMarkersLayer $layer, string $oClusterJs) : string
    {
        $color = $layer->getColor() ?? $this->getLayerColors()[$this->getWidget()->getLayerIndex($layer)];
        $caption = str_replace("'", "\\'", trim($this->escapeString($layer->getCaption(), true, false), '"'));
        
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
        
        foreach ($widget->getLayers() as $layer) {
            if (($layer instanceof MarkerMapLayerInterface) && $layer->isClusteringMarkers() !== false) {
                $includes[] = '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.MARKERCLUSTER_CSS') . '"/>';
                $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.MARKERCLUSTER_JS') . '"></script>';
                break;
            }
            if (($layer instanceof CustomProjectionMapLayerInterface) && $layer->hasProjectionDefinition() !== false) {
                $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.PROJ4.PROJ4JS') . '"></script>';
                $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.PROJ4.PROJ4LEAFLETJS') . '"></script>';
                break;
            }
            
            if (($layer instanceof iCanBeDragAndDropTarget) && $layer->isDropTarget()) {
                if ($layer instanceof GeoJsonMapLayerInterface) {
                    $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.TRUF.JS') . '"></script>';
                    /*$includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.TRUF.HELPERS') . '"></script>';
                    $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.TRUF.BOOLEAN_POINT_IN_POLYGON') . '"></script>';
                    */
                }
            }
            
            if ($this->hasLeafletDraw() === true) {
                $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.DRAW.JS') . '"></script>';
                $includes[] = '<script src="' . $f->buildUrlToSource('LIBS.LEAFLET.GEOMAN.JS') . '"></script>';
                $includes[] = '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.DRAW.CSS') . '"/>';
                $includes[] = '<link rel="stylesheet" href="' . $f->buildUrlToSource('LIBS.LEAFLET.GEOMAN.CSS') . '"/>';
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
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null) : string
    {
        $widget = $this->getWidget();
        $rows = '';
        
        if ($action !== null && $action->isDefinedInWidget() && $action->getWidgetDefinedIn() instanceof DataButton) {
            $customMode = $action->getWidgetDefinedIn()->getInputRows();
        } else {
            $customMode = null;
        }
        
        switch (true) {
            case $customMode === DataButton::INPUT_ROWS_ALL:
            case $action === null:
                $rows = "{$this->buildJsLeafletVar()}?._exfState.selectedFeature ? {$this->buildJsLeafletVar()}?._exfState.selectedFeature.properties.data : []";
                break;
            
            // If the button requires none of the rows explicitly
            case $customMode === DataButton::INPUT_ROWS_NONE:
                return '{}';
                
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            case $action instanceof iReadData:
                return $this->getFacade()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter($action);
            
            default:
                $rows = $this->buildJsLeafletGetSelectedRows();
        }
        return "{oId: {$this->buildJsLeafletVar()}?._exfState.selectedFeature ? {$this->buildJsLeafletVar()}?._exfState.selectedFeature.properties.object : '{$widget->getMetaObject()->getId()}', rows: $rows}";
    }
    
    protected function buildJsLeafletGetSelectedRows() : string
    {
        return "{$this->buildJsLeafletVar()}?._exfState.selectedFeature ? [{$this->buildJsLeafletVar()}?._exfState.selectedFeature.properties.data] : []";
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
        return "{$this->buildJsLeafletVar()}.fire('exfRefresh').invalidateSize()";
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
     * @return string
     */
    protected function buildJsMapDrop() : string
    {
        $widget = $this->getWidget();
        
        $dropLayers = [];
        $dropScripts = [];
        foreach ($widget->getLayers() as $layer) {
            $dropLayers[] = $layer;
            if (($layer instanceof iCanBeDragAndDropTarget) && $layer->isDropTarget()) {
                $dropLayers[] = $layer;
                $dropDataBuilderJs = '';
                /* @var $dropPart \exface\Core\Widgets\Parts\DragAndDrop\DropToAction */
                foreach ($layer->getDropToActions() as $dropPart) {
                    $dropTriggerEl = $this->getFacade()->getElement($dropPart->getActionTrigger());
                    $dropScripts[] = $dropTriggerEl->buildJsClickFunction($dropPart->getAction(), 'oRequestData');
                    foreach ($dropPart->getIncludeTargetColumnMappings() as $map) {
                        $dropDataBuilderJs .= "\n
                    oDroppedData.rows[0]['{$map->getToExpression()->__toString()}'] = oTargetRow['{$map->getFromExpression()->__toString()}'];";
                    }
                }
            }
        }
        
        if (empty($dropLayers)) {
            return '';
        }
        
        if (count($dropScripts) > 1) {
            throw new FacadeRuntimeError('Multiple drop zones in a map currently not supported');
        }
        
        $onDropJs = $dropScripts[0];
        
        return <<<JS
        setTimeout(() => {
            const wrapperDiv = document.getElementById('{$this->getIdLeaflet()}');
            
             wrapperDiv.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
                const oMap = {$this->buildJsLeafletVar()};
                // Get the coordinates where the element was dropped
                var latlng = oMap.mouseEventToLatLng(e);
                
                const layers = oMap._layers;
                const shapes = Object.values(layers).filter(item => item?.feature && item?.feature?.geometry?.type === 'Polygon');
                const point = turf.point([latlng?.lng, latlng?.lat]);
                const matchingPolygon = shapes.forEach(shape => {
                    const polygon = turf.polygon(shape?.feature?.geometry?.coordinates);
                    if(turf.booleanPointInPolygon(point, polygon)) {
                        shape.setStyle({ weight: 6 })
                        e.dataTransfer.dropEffect = "copy";
                    } else {
                        shape.setStyle({ weight: 3 })
                    }
                })
            });
            
            // Add event listener for drop event on the map
            wrapperDiv.addEventListener('drop', function (e) {
                e.preventDefault()
                const oMap = {$this->buildJsLeafletVar()};
                // Get the coordinates where the element was dropped
                var latlng = oMap.mouseEventToLatLng(e);
                
                const layers = oMap._layers;
                const shapes = Object.values(layers).filter(item => item?.feature && item?.feature?.geometry?.type === 'Polygon');
                const point = turf.point([latlng?.lng, latlng?.lat]);
                shapes.forEach(s => {
                    s.setStyle({ weight: 3 })
                });
                const matchingPolygon = shapes.find(shape => {
                    const polygon = turf.polygon(shape?.feature?.geometry?.coordinates);
                    return turf.booleanPointInPolygon(point, polygon);
                });
                var oTargetRow = (matchingPolygon?.feature?.properties?.data || {});  
                var oDroppedData = oRequestData = JSON.parse(e.dataTransfer.getData('dataSheet') || '{}');   
                {$dropDataBuilderJs}       

                $(wrapperDiv).removeClass('mouseDown');
                console.log('Dropped data', oDroppedData);
                console.log('Dropped target', oTargetRow);
                
                {$onDropJs}
            });
        }, 100);
JS;
    }
    
    protected function hasLeafletDraw() : bool
    {
        foreach ($this->getWidget()->getLayers() as $layer) {
            if (($layer instanceof DataShapesLayer) && $layer->isEditable() === true) {
                return true;
            }
        }
        return false;
    }
    
    protected function buildJsLeafletDrawInit(string $oMapJs) : string
    {
        if (! $this->hasLeafletDraw()) {
            return '';
        }
        
        $editableLayer = null;
        foreach ($this->getWidget()->getLayers() as $layer) {
            if (($layer instanceof DataShapesLayer) && $layer->isEditable() === true) {
                if ($editableLayer !== null) {
                    throw new FacadeRuntimeError('Multiple editable map layers currently not supported!');
                }
                $editableLayer = $layer;
            }
        }
        
        $errorColor = $this->getFacade()->getSemanticColors()['~ERROR'] ?? '#e1e100';
        
        return <<<JS

(function(oMap){
    var editableLayers = oMap._exfLayers[{$this->getWidget()->getLayerIndex($editableLayer)}].layer;
    var oDrawOpts = {
        position: 'topright',
        draw: {
            polyline: false,
            polygon: {
                clickable: true,
                allowIntersection: false, // Restricts shapes to simple polygons
                drawError: {
                    color: '{$errorColor}', // Color the shape will turn when intersects
                    message: '<strong>Oh snap!<strong> you can\'t draw that!' // Message that will show when intersect
                },
                shapeOptions: {
                    color: '#0000ff'
                }
            },
            circle: false, // Turns off this drawing tool
            simpleShape: false,
            rectangle: {
                shapeOptions: {
                    clickable: false
                }
            },
            marker: false,
        },
        edit: {
            featureGroup: editableLayers, //REQUIRED!!
            remove: false
        }
    };

    // Adjust which controls on geoman to be shown
    oMap.pm.addControls({
        drawMarker: false,
        drawPolygon: true,
        editMode: true,
        drawPolyline: false,
        removalMode: false,
        drawText: false,
        drawCircleMarker: false,
        rotateMode: true,
        cutPolygon: false,
    });
})($oMapJs);
JS;
    }
    
    /**
     *
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\EChartsTrait
     */
    protected abstract function registerLiveReferenceAtLinkedElements();
}