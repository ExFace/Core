<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\StringDataType;
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
    
    private $autoZoomToSeeAll = null;
    
    private $visibility = null;
    
    /**
     * The type (prototype class) of the layer.
     * 
     * Use one of the built-in classes (e.g. `DataMarkers`, `GeoJSON`, etc.) or 
     * specify any PHP class implementing the MapLayerInterface (e.g. 
     * `\exface\Core\Widgets\Parts\Maps\DataMarkersLayer`).
     * 
     * @uxon-property type
     * @uxon-type [DataLine,DataMarkers,DataSelectionMarker,GeoJSON]
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
    
    /**
     *
     * @return bool|NULL
     */
    public function getAutoZoomToSeeAll() : ?bool
    {
        return $this->autoZoomToSeeAll;
    }
    
    /**
     * Set to TRUE to zoom in or out to make all data visible every time the layer data is loaded.
     *
     * @uxon-property auto_zoom_to_see_all
     * @uxon-type boolean
     *
     * @param bool $value
     * @return AbstractDataLayer
     */
    public function setAutoZoomToSeeAll(bool $value) : AbstractDataLayer
    {
        $this->autoZoomToSeeAll = $value;
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