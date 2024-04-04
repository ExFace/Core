<?php
namespace exface\Core\Widgets\Parts\Maps\BaseMaps;

use exface\Core\Widgets\Parts\Maps\AbstractBaseMap;
use exface\Core\Widgets\Map;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;

/**
 * Shows ArcGIS base maps
 * 
 * @author Andrej Kabachnik
 *
 */
class EsriArcGisMap extends AbstractBaseMap
{
    const BASEMAPS = [
        "Topographic" => "Topographic",
        "Streets" => "Streets",
        "NationalGeographic" => "National Geographic",
        "Oceans" => "Oceans",
        "Gray" => "Gray",
        "DarkGray" => "Dark Gray",
        "Imagery" => "Imagery",
        "ImageryClarity" => "Imagery (Clarity)",
        "ImageryFirefly" => "Imagery (Firefly)",
        "ShadedRelief" => "Shaded Relief",
        "Physical" => "Physical"
    ];
    
    private $basemap = null;
    
    private $showLabels = true;
    
    public function __construct(Map $widget, UxonObject $uxon = null)
    {
        parent::__construct($widget, $uxon);
        $widget->getWorkbench()->eventManager()->addListener(OnFacadeWidgetRendererExtendedEvent::getEventName(), [$this, 'onLeafletRendererRegister']);
    }
    
    /**
     * 
     * @return string
     */
    public function getBasemap() : string
    {
        return $this->basemap ?? array_keys(self::BASEMAPS)[0];
    }
    
    /**
     * Which base map to show
     * 
     * @uxon-property basemap
     * @uxon-type [Topographic,Streets,NationalGeographic,Oceans,Gray,DarkGray,Imagery,ImageryClarity,ImageryFirefly,ShadedRelief,Physical]
     * @uxon-default Topographic
     * 
     * @param string $value
     * @return EsriArcGisMap
     */
    public function setBasemap(string $value) : EsriArcGisMap
    {
        $this->basemap = $value;
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
        
        if (! $facadeElement || ! method_exists($facadeElement, 'addLeafletLayerRenderer') || ! method_exists($facadeElement, 'addLeafletHeadTag')) {
            return;
        }
        
        $facadeElement->addLeafletHeadTag('<script src="' . $event->getFacade()->buildUrlToSource('LIBS.LEAFLET.ESRI.JS') . '"></script>');
        $leafletVar = $facadeElement->buildJsLeafletVar();
        
        $facadeElement->addLeafletLayerRenderer(function(MapLayerInterface $layer) use ($leafletVar) {
        
            if ($layer !== $this) {
                return '';
            }
            
            $basemap = $this->getBasemap();
            $labelsLayer = '';
            if ($layer->getShowLabels()) {
                if ($basemap === 'ShadedRelief'
                || $basemap === 'Oceans'
                || $basemap === 'Gray'
                || $basemap === 'DarkGray'
                || $basemap === 'Terrain') {
                    $labelsLayer = $basemap . 'Labels';
                } elseif (strstr($basemap, 'Imagery') !== false) {
                    $labelsLayer = 'ImageryLabels';
                }
            }
            
            return <<<JS

(function(){
    var oBaseMap = L.esri.basemapLayer('{$basemap}');
    var sLabelLayerName = '{$labelsLayer}';
    var oLabelLayer;
    if (sLabelLayerName) {
        var oLabelLayer = L.esri.basemapLayer(sLabelLayerName);
        oBaseMap.on('add', function(oEvent){
            {$leafletVar}.addLayer(oLabelLayer);
        });
        oBaseMap.on('remove', function(oEvent){
            {$leafletVar}.removeLayer(oLabelLayer);
        });
    }
    return oBaseMap;
})()

JS;
        });
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption() : ?string
    {
        return self::BASEMAPS[$this->getBasemap()];
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowLabels() : bool
    {
        return $this->showLabels;
    }
    
    /**
     * Set to FALSE to remove labels (city names, etc.) if possible.
     * 
     * By default all maps include labels. For the following base maps labels can be removed:
     * 
     * - ShadedRelief
     * - Oceans
     * - Gray
     * - DarkGray
     * - Terrain
     * - Imagery maps
     * 
     * @uxon-property show_labels
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return EsriArcGisMap
     */
    public function setShowLabels(bool $value) : EsriArcGisMap
    {
        $this->showLabels = $value;
        return $this;
    }
}