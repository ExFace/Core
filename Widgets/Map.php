<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\Traits\iHaveConfiguratorTrait;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\Traits\PrefillValueTrait;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface;

/**
 * A map with support for different mapping data providers and data layers.
 *
 * @author Andrej Kabachnik
 *        
 */
class Map extends AbstractWidget implements
    iHaveToolbars, 
    iHaveButtons, 
    iHaveHeader, 
    iHaveConfigurator, 
    iFillEntireContainer
{
    use iHaveButtonsAndToolbarsTrait;
    use PrefillValueTrait;
    use iHaveConfiguratorTrait {
        setConfiguratorWidget as setConfiguratorWidgetViaTrait;
    }
    
    const COORDINATE_LAT = 'latitude';
    
    const COORDINATE_LON = 'longitude';
    
    const PART_FOLDER_BASE_MAPS = 'BaseMaps';
    
    private $layers = [];
    
    private $baseMaps = [];
    
    private $providers = null;
    
    private $centerLatitudeAttributeAlias = null;
    
    private $centerLatitude = null;
    
    private $centerLongitudeAttributeAlias = null;
    
    private $centerLongitude = null;
    
    private $centerViaGps = null;
    
    private $zoom = null;
    
    private $showFullScreenButton = true;
    
    private $showGpsLocateButton = true;
    
    private $showScale = true;

    /**
     * @var bool
     */
    private $hide_header = null;
    
    /**
     *
     * @return BaseMapInterface[]
     */
    public function getBaseMaps() : array
    {
        return $this->baseMaps;
    }
    
    public function getBaseMap(int $index) : ?BaseMapInterface
    {
        return $this->baseMaps[$index];
    }
    
    public function getBaseMapIndex(BaseMapInterface $baseMap) : ?int
    {
        return array_search($baseMap, $this->getBaseMaps());
    }
    
    /**
     * BaseMaps to show on top of the map
     *
     * @uxon-property base_maps
     * @uxon-type \exface\Core\Widgets\Parts\Maps\AbstractMapLayer[]
     * @uxon-template [{"type": ""}]
     *
     * @param UxonObject $uxon
     * @throws WidgetConfigurationError
     * @return Map
     */
    public function setBaseMaps(UxonObject $uxon) : Map
    {
        foreach ($uxon->getPropertiesAll() as $nr => $baseMapUxon) {
            $type = $baseMapUxon->getProperty('type');
            if (! $type) {
                throw new WidgetConfigurationError($this, 'No map baseMap type specified for baseMap ' . $nr);
            }
            $class = $this->getLayerClassFromType($type, self::PART_FOLDER_BASE_MAPS);
            $baseMap = new $class($this, $baseMapUxon);
            $this->baseMaps[] = $baseMap;
        }
        return $this;
    }
    
    /**
     * 
     * @param callable $filterCallback
     * @return MapLayerInterface[]
     */
    public function getLayers(callable $filterCallback = null) : array
    {
        if ($filterCallback !== null){
            return array_values(array_filter($this->layers, $filterCallback));
        }
        return $this->layers;
    }
    
    /**
     * 
     * @return array
     */
    public function getDataLayers() : array
    {
        return $this->getLayers(function($layer){
            return ($layer instanceof \exface\Core\Widgets\Parts\Maps\AbstractDataLayer);
        });
    }
    
    public function getLayer(int $index) : ?MapLayerInterface
    {
        return $this->layers[$index];
    }
    
    public function getLayerIndex(MapLayerInterface $layer) : ?int
    {
        return array_search($layer, $this->getLayers());
    }
    
    /**
     * Layers to show on top of the map
     * 
     * @uxon-property layers
     * @uxon-type \exface\Core\Widgets\Parts\Maps\AbstractDataLayer[]
     * @uxon-template [{"type": ""}]
     * 
     * @param UxonObject $uxon
     * @throws WidgetConfigurationError
     * @return Map
     */
    public function setLayers(UxonObject $uxon) : Map
    {
        foreach ($uxon->getPropertiesAll() as $nr => $layerUxon) {
            $type = $layerUxon->getProperty('type');
            if (! $type) {
                throw new WidgetConfigurationError($this, 'No map layer type specified for layer ' . $nr);
            }
            $class = $this->getLayerClassFromType($type);
            $layer = new $class($this, $layerUxon);
            $this->layers[] = $layer;
        }
        return $this;
    }
    
    /**
     * 
     * @param string $layerType
     * @return string
     */
    protected function getLayerClassFromType(string $layerType, string $subfolder = null) : string
    {
        if (substr($layerType, 0, 1) === '\\') {
            $class = $layerType;
        } else {
            $class = __NAMESPACE__ . '\\Parts\\Maps\\' . ($subfolder !== null ? $subfolder . '\\' : '') . $layerType;
            if ($subfolder === null && ! StringDataType::endsWith($class, 'Layer')) {
                $class .= 'Layer';
            }
        }        
        return $class;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        yield $this->getConfiguratorWidget();
        
        foreach ($this->getLayers() as $layer) {
            yield from $layer->getWidgets();
        }
        
        foreach ($this->getToolbars() as $toolbar) {
            yield $toolbar;
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getWidth()
     */
    public function getWidth()
    {
        if (parent::getWidth()->isUndefined()) {
            $this->setWidth('max');
        }
        return parent::getWidth();
    }

    /**
     * Set to true to hide the top toolbar, which generally will contain filters and other settings
     *
     * @uxon-property hide_header
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::getHideHeader()
     */
    public function getHideHeader() : ?bool
    {
        return $this->hide_header;
    }

    public function setHideHeader(bool $value) : iHaveHeader
    {
        $this->hide_header = $value;
        return $this;
    }

    /**
     * A Chart can be prefilled just like all the other data widgets, but only if it has it's own data. If the data is fetched from
     * a linked widget, the prefill does not make sense and will be ignored. But the linked widget will surely be prefilled, so the
     * the chart will get the correct data anyway.
     * 
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Data::prefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        // Do not do anything, if the value is already set explicitly (e.g. a fixed value)
        if (! $this->isPrefillable()) {
            return;
        }
        
        if ($this->isCenterBoundToAttributes()) {
            $this->doPrefillCoordinate($data_sheet, self::COORDINATE_LAT);     
            $this->doPrefillCoordinate($data_sheet, self::COORDINATE_LON); 
        }
        
        foreach ($this->getLayers() as $layer) {
            $layer->doPrefill($data_sheet);
        }
        return;
    }
    
    protected function doPrefillCoordinate(DataSheetInterface $dataSheet, string $coordinate) 
    {
        switch ($coordinate) {
            case self::COORDINATE_LAT:
                $attrAlias = $this->getCenterLatitudeAttributeAlias();
                $property = 'center_latitude';
                break;
            case self::COORDINATE_LON:
                $attrAlias = $this->getCenterLongitudeAttributeAlias();
                $attrAlias = $this->getCenterLatitudeAttributeAlias();
                $property = 'center_longitude';
        }
        
        $colName = $this->getPrefillExpression($dataSheet, $this->getMetaObject(), $attrAlias);
        if ($col = $dataSheet->getColumns()->getByExpression($colName)) {
            if (count($col->getValues(false)) > 1 && $this->getAggregator()) {
                // TODO #OnPrefillChangeProperty
                $valuePointer = DataPointerFactory::createFromColumn($col);
                $value = $col->aggregate($this->getAggregator());
            } else {
                $valuePointer = DataPointerFactory::createFromColumn($col, 0);
                $value = $valuePointer->getValue();
            }
            // Ignore empty values because if value is a live-references as the ref would get overwritten
            // even without a meaningfull prefill value
            if ($this->isCenterBoundByReference() === false || ($value !== null && $value != '')) {
                if ($coordinate === self::COORDINATE_LAT) {
                    $this->setCenterLatitude($value);
                } else {
                    $this->setCenterLongitude($value);
                }
                $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, $property, $valuePointer));
            }
        }
        
        return;
    }
    
    public function isCenterBoundToAttributes() : bool
    {
        return $this->getCenterLatitudeAttributeAlias() !== null && $this->getCenterLongitudeAttributeAlias() !== null;
    }
    
    public function isCenterBoundByReference() : bool
    {
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        if ($this->isCenterBoundToAttributes()) {
            if ($colName = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getCenterLatitudeAttributeAlias())) {
                if (! $data_sheet->getColumns()->getByExpression($colName)) {
                    $data_sheet->getColumns()->addFromExpression($colName);
                }
            }
            if ($colName = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getCenterLongitudeAttributeAlias())) {
                if (! $data_sheet->getColumns()->getByExpression($colName)) {
                    $data_sheet->getColumns()->addFromExpression($colName);
                }
            }
        }
        
        foreach ($this->getLayers() as $layer) {
            $data_sheet = $layer->prepareDataSheetToPrefill($data_sheet);
        }
        
        return $data_sheet;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        foreach ($this->getLayers() as $layer) {
            $data_sheet = $layer->prepareDataSheetToRead($data_sheet);
        }
        return $data_sheet;
    }

    /**
     * Returns an array of button widgets, that are explicitly bound to a double click on a data element
     *
     * @param string $mouse_action            
     * @return DataButton[]
     */
    public function getButtonsBoundToMouseAction($mouse_action)
    {
        $result = array();
        foreach ($this->getButtons() as $btn) {
            if ($btn instanceof DataButton) {
                if ($btn->getBindToMouseAction() == $mouse_action) {
                    $result[] = $btn;
                }
            }        
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'DataButton';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
    {
        return null;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType() : string
    {
        return 'MapConfigurator';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveToolbars::getToolbarWidgetType()
     */
    public function getToolbarWidgetType()
    {
        return 'DataToolbar';
    }
    
    /**
     * TODO #map-configurator make sure, only a ChartConfigurator can be used for charts!
     * This is tricky because a chart with a data link must have the same configurator, as
     * the linked data, so that they are update simultaniously. It relly MUST be the same
     * widget. However, if the linked data widget was already instantiated, it already has
     * a DataConfigurator, which now needs to get transformed into a chart configurator. This
     * transformation is basically the TODO. Since a ChartConfigurator also is a DataConfigurator,
     * it should be possible to use it back in the data widget. Of course, if there will be 
     * more DataConfigurators (e.g. the already existing DataTableConfigurator), it might be
     * better to make the ChartConfigurator wrap a DataConfigurator - to be discussed!
     *  
     * @see \exface\Core\Widgets\Traits\iHaveConfiguratorTrait::setConfiguratorWidget()
     */
    public function setConfiguratorWidget(iConfigureWidgets $widget) : iHaveConfigurator
    {
        if (! $widget instanceof ChartConfigurator && $widget instanceof DataConfigurator) {
            $configurator = WidgetFactory::create($this->getPage(), $this->getConfiguratorWidgetType(), $this);
            $configurator->setDataConfigurator($widget);
        } else {
            $configurator = $widget;
        }
        return $this->setConfiguratorWidgetViaTrait($configurator);
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getCenterLatitudeAttributeAlias() : ?string
    {
        return $this->centerLatitudeAttributeAlias;
    }
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    public function getCenterLatitudeAttribute() : ?MetaAttributeInterface
    {
        return $this->centerLatitudeAttributeAlias === null ? null : $this->getMetaObject()->getAttribute($this->centerLatitudeAttributeAlias);
    }
    
    /**
     * Alias of the attribute that will contain the latitude of the center of the map
     * 
     * @uxon-property center_latitude_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return Chart
     */
    public function setCenterLatitudeAttributeAlias(string $value) : Map
    {
        $this->centerLatitudeAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getCenterLatitude() : ?float
    {
        return $this->centerLatitude;
    }
    
    /**
     * Latitude of the center of the map
     * 
     * @uxon-property center_latitude
     * @uxon-type number
     * 
     * @param float $value
     * @return Map
     */
    public function setCenterLatitude(float $value) : Map
    {
        $this->centerLatitude = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getCenterLongitudeAttributeAlias() : ?string
    {
        return $this->centerLongitudeAttributeAlias;
    }
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    public function getCenterLongitudeAttribute() : ?MetaAttributeInterface
    {
        return $this->centerLongitudeAttributeAlias === null ? null : $this->getMetaObject()->getAttribute($this->centerLongitudeAttributeAlias);
    }
    
    /**
     * Alias of the attribute that will contain the longitude of the center of the map
     *
     * @uxon-property center_longitude_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param float $value
     * @return Chart
     */
    public function setCenterLongitudeAttributeAlias(string $value) : Map
    {
        $this->centerLongitudeAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getCenterLongitude() : ?float
    {
        return $this->centerLongitude;
    }
    
    /**
     * Longitude of the center of the map
     *
     * @uxon-property center_longitude
     * @uxon-type number
     *
     * @param float $value
     * @return Map
     */
    public function setCenterLongitude(float $value) : Map
    {
        $this->centerLongitude = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getCenterViaGps() : bool
    {
        return $this->centerViaGps ?? ($this->centerLatitudeAttributeAlias === null && $this->centerLongitudeAttributeAlias === null);
    }
    
    /**
     * Set to TRUE to use the current location as map center
     *
     * @uxon-property center_via_gps
     * @uxon-type boolean
     *
     * @param float $value
     * @return Chart
     */
    public function setCenterViaGps(bool $value) : Map
    {
        $this->centerViaGps = $value;
        return $this;
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getZoom() : ?int
    {
        return $this->zoom;
    }
    
    /**
     * The initial zoom factor
     * 
     * The value depends on the rendering library used by the facade, however powers of 2
     * are very common: 1 for complete zoom out (whole earth or even more), 2 double zoom, 3
     * for 4x zoom, etc.
     * 
     * @uxon-property zoom
     * @uxon-type integer
     * 
     * @param int $value
     * @return Map
     */
    public function setZoom(int $value) : Map
    {
        $this->zoom = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowFullScreenButton() : bool
    {
        return $this->showFullScreenButton;
    }
    
    /**
     * Set to FALSE to disallow switching to full-screen view.
     * 
     * @uxon-property show_full_screen_button
     * @uxon-type boolean
     * @uxon-defaul true
     * 
     * @param bool $value
     * @return Map
     */
    public function setShowFullScreenButton(bool $value) : Map
    {
        $this->showFullScreenButton = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowGpsLocateButton() : bool
    {
        return $this->showGpsLocateButton;
    }

    /**
     * Set to FALSE to disallow moving the view to the current location provided by GPS or other location services.
     * 
     * @uxon-property show_gps_location_button
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return Map
     */
    public function setShowGpsLocateButton(bool $value) : Map
    {
        $this->showGpsLocateButton = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowScale() : bool
    {
        return $this->showScale;
    }
    
    /**
     * Set to FALSE to hide the scale from the map.
     * 
     * @uxon-property show_scale
     * @uxon-type bool
     * @uxon-default true
     * 
     * @param bool $value
     * @return Map
     */
    public function setShowScale(bool $value) : Map
    {
        $this->showScale = $value;
        return $this;
    }
}