<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Widgets\Traits\iHaveVisibilityTrait;

/**
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractMapLayer extends AbstractMapPart implements MapLayerInterface
{
    use iHaveCaptionTrait;
    
    use iHaveVisibilityTrait;
    
    private $visibility = null;

    private $showPopupOnClick = true;
    
    private ?AutoZoom $autoZoom = null;
    
    /**
     * The type (prototype class) of the layer.
     * 
     * Use one of the built-in classes (e.g. `DataMarkers`, `GeoJSON`, etc.) or 
     * specify any PHP class implementing the MapLayerInterface (e.g. 
     * `\exface\Core\Widgets\Parts\Maps\DataMarkersLayer`).
     * 
     * @uxon-property type
     * @uxon-type [DataLines,DataMarkers,DataPoints,DataShapes,DataSelectionMarker,DataSelectionShapeMarker,GeoJSON,WebTiles,WMS]
     * @uxon-required true
     * 
     * @return MapLayerInterface
     */
    protected function setType() : MapLayerInterface
    {
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getType() : string
    {
        $class = PhpClassDataType::findClassNameWithoutNamespace($this);
        return StringDataType::substringBefore($class, 'Layer', $class);
    }

    public function getShowPopupOnClick(): bool
    {
        return $this->showPopupOnClick;
    }

    /**
     *
     * @return AutoZoom|null
     */
    public function getAutoZoom() : ?AutoZoom
    {
        if (! $this->supportsAutoZoom()) {
            return null;
        }
        if ($this->autoZoom === null) {
            if (null !== $defaults = $this->getMap()->getAutoZoomDefaults()) {
                $this->autoZoom = new AutoZoom($this->getMap(), $defaults->copy(), $this);
            }
        }
        return $this->autoZoom;
    }

    /**
     * 
     * @return bool
     */
    public function supportsAutoZoom() : bool
    {
        return false;
    }

    /**
     * Configure auto-zooming when the layer is refreshed or loads new data
     * 
     * If the map of this layer has `auto_zoom` defaults, the configuration done here will be applied on-top
     * of these defaults, overwriting corresponding default values.
     * 
     * @uxon-property auto_zoom
     * @uxon-type \exface\Core\Widgets\Parts\Maps\AutoZoom
     * @uxon-template {"zoom_in": false, "zoom_out": true}
     * 
     * @param UxonObject $uxon
     * @return MapLayerInterface
     */
    protected function setAutoZoom(UxonObject $uxon) : MapLayerInterface
    {
        if (! $this->supportsAutoZoom()) {
            throw new WidgetConfigurationError($this->getMap(), 'Map layers of type ' . $this->getType() . ' do not support auto-zoom.');
        }
        if ($this->autoZoom === null && null !== $defaults = $this->getMap()->getAutoZoomDefaults()) {
            $uxon = $defaults->extend($uxon);
        }
        $this->autoZoom = new AutoZoom($this->getMap(), $uxon, $this);
        return $this;
    }
    

    /**
     * Set to TRUE to zoom in or out to make all data visible every time the layer data is loaded.
     *
     * @deprecated use auto_zoom instead
     *
     * @param bool $value
     * @return AbstractMapLayer
     */
    protected function setAutoZoomToSeeAll(bool $value) : AbstractMapLayer
    {
        // Ignore legacy UXON syntax if auto-zoom is not supported at all
        if (! $this->supportsAutoZoom()) {
            return $this;
        }
        $defaults = $this->getMap()->getAutoZoomDefaults();
        $uxon = $defaults ? $defaults->copy() : new UxonObject();
        if ($value === false) {
            $uxon->setProperty('disabled', true);
        }
        $this->autoZoom = new AutoZoom($this->getMap(), $uxon, $this);
        return $this;
    }
    
    /**
     * @deprecated use auto_zoom instead
     * 
     * @param int $value
     * @return AbstractMapLayer
     */
    protected function setAutoZoomMax(int $value) : AbstractMapLayer
    {
        // Ignore legacy UXON syntax if auto-zoom is not supported at all
        if (! $this->supportsAutoZoom()) {
            return $this;
        }
        if ($this->getAutoZoom() === null) {
            $this->setAutoZoom(new UxonObject([
                'zoom_max' => $value,
            ]));
        } else {
            $this->autoZoom->setZoomMax($value);
        }
        return $this;
    }
    
    /**
     * 
     * Show popup on click event with provided information
     * 
     * @uxon-property show_popup_on_click
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param boolean $value
     * @return AbstractMapLayer
     *
     */
    protected function setShowPopupOnClick(bool $value) : AbstractMapLayer
    {
        $this->showPopupOnClick = $value;
        return $this;
    }


    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface::getWidgets()
     */
    public function getWidgets() : \Generator
    {
        yield from [];
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface::getIndex()
     */
    public function getIndex() : int
    {
        return $this->getMap()->getLayerIndex($this);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $sheet): DataSheetInterface
    {
        return $sheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $sheet): DataSheetInterface
    {
        return $sheet;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public static function getUxonSchemaClass() : ?string
    {
        return MapLayerUxonSchema::class;
    }
}