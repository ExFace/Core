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
 * ### Auto zoom direction
 * 
 * - set `zoom_in` to true to force the map to zoom into the map again if the data reloads.
 * - set `zoom_out` to true to force the map to zoom out again if the data reloads.
 * 
 * ### Include all layers
 * 
 * If set on the `Map` widget itself the whole map will always include all layers into the auto zoom. If set on a specific `layer`, the auto zoom will be extended to all layers that are loaded before that layer.
 * 
 * ### Zoom Levels
 * 
 * - The zoom level of `0` shows the entire world map. 
 * - Level `1` will zoom-in by a factor of two. (Each increase in zoom level doubles the resolution in both width and height.)
 * 
 * **Disclaimer**: Most maps allow a zoom-level of about 18 (which is street-level).
 * 
 * ### Disable Zoom
 * - use `disabled` to disable the zoom on your level.
 * - If you use it in a layer, it will disable the zoom of that layer.
 * - If you use it in the map widget it will disable the zoom for the whole map. Keep in mind, that each layer can override this setting with their disabled within their auto_zoom object.
 * 
 * @author Andrej Kabachnik
 * @summary_author Miriam Seitz
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
     * Set to true to enforce an automatic zoom in on data changes.
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
     * Set to true to enforce an automatic zoom out on data changes.
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
     * Maximum zoom level. The map will not be zoomed in any further.
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
     * Minimum zoom level. The map will not be zoomed out any further.
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
     * Disable zoom on this level.
     * 
     * @uxon-property disabled
     * @uxon-type boolean
     * @uxon-default false
     * 
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
     * @param bool $trueOrFalse
     * @return $this
     */
    public function setIncludeOtherLayers(bool $trueOrFalse) : AutoZoom
    {
        $this->includeOtherLayers = $trueOrFalse;
        return $this;
    }
}