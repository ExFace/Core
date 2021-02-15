<?php
namespace exface\Core\Widgets\Parts\Maps\BaseMaps;

use exface\Core\Widgets\Parts\Maps\AbstractBaseMap;
use exface\Core\Widgets\Map;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Events\Facades\OnFacadeWidgetRendererExtendedEvent;

/**
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
     * 
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
        
        $facadeElement->addLeafletLayerRenderer(function(MapLayerInterface $layer){
        
            
            if ($layer !== $this) {
                return '';
            }
            
            return "L.esri.basemapLayer('{$this->getBasemap()}')";
            
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
}