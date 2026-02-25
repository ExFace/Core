<?php

namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\CommonLogic\Traits\ICanBeConvertedToUxonTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\Map;
use exface\Core\Widgets\Parts\Maps\Interfaces\DataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;

/**
 * Allows to zoom-in and out automatically when the layer is refreshed
 * 
 * The zoom level of `0` shows the entire world map. Level `1` will zoom-in by a factor of two. Most maps allow
 * a zoom-level of about 18 (which is street-level).
 * 
 */
class AutoZoom implements WidgetPartInterface
{
    use ICanBeConvertedToUxonTrait;

    private Map $map;
    private ?MapLayerInterface $layer;
    private WorkbenchInterface $workbench;
    private ?UxonObject $uxon = null;
    
    private bool $zoomIn = false;
    private bool $zoomOut = true;
    private ?float $zoomMax = null;
    private float $zoomMin = 0;
    private bool $includeOtherLayers = false;
    
    private bool $disabled = false;

    public function __construct(Map $widget, UxonObject $uxon = null, ?MapLayerInterface $layer = null)
    {
        $this->map = $widget;
        $this->layer = $layer;
        $this->workbench = $widget->getWorkbench();
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
            $this->uxon = $uxon;
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->getMap();
    }

    /**
     *
     * @return Map
     */
    public function getMap() : Map
    {
        return $this->map;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->layer instanceof DataMapLayerInterface ? $this->layer->getMetaObject() : $this->map->getMetaObject();
    }

    public function getZoomIn() : bool
    {
        return $this->zoomIn;
    }

    /**
     * Minimum zoom level
     *
     * @uxon-property zoom_in
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $zoomIn
     * @return $this
     */
    public function setZoomIn(bool $zoomIn) : AutoZoom
    {
        $this->zoomIn = $zoomIn;
        return $this;
    }

    public function getZoomOut() : bool
    {
        return $this->zoomOut;
    }

    /**
     * Minimum zoom level
     *
     * @uxon-property zoom_out
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $zoomOut
     * @return $this
     */
    public function setZoomOut(bool $zoomOut) : AutoZoom
    {
        $this->zoomOut = $zoomOut;
        return $this;
    }

    public function getZoomMax() : ?float
    {
        return $this->zoomMax;
    }

    /**
     * Maximum zoom level
     *
     * @uxon-property zoom_max
     * @uxon-type number
     *
     * @param float|null $zoomMax
     * @return $this
     */
    public function setZoomMax(?float $zoomMax) : AutoZoom
    {
        $this->zoomMax = $zoomMax;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getZoomMin() : ?float
    {
        return $this->zoomMin;
    }

    /**
     * Minimum zoom level
     * 
     * @uxon-property zoom_min
     * @uxon-type number
     * @uxon-default 0
     * 
     * @param float|null $zoomMin
     * @return $this
     */
    public function setZoomMin(?float $zoomMin) : AutoZoom
    {
        $this->zoomMin = $zoomMin;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDisabled() : bool
    {
        return $this->disabled;
    }

    /**
     * @param bool $trueOrFalse
     * @return $this
     */
    public function setDisabled(bool $trueOrFalse) : AutoZoom
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIncludeOtherLayers() : bool
    {
        return $this->includeOtherLayers;
    }
    /**
     * Set to TRUE to make sure all layers with auto_zoom remain visible after zooming this layer
     *
     * @uxon-property include_other_layers
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param float|null $zoomMin
     * @return $this
     */
    public function setIncludeOtherLayers(bool $trueOrFalse) : AutoZoom
    {
        $this->includeOtherLayers = $trueOrFalse;
        return $this;
    }
}