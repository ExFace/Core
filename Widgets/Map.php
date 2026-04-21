<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Widgets\Parts\Maps\Interfaces\DataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\MapLayerUxonSchema;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\Traits\iHaveConfiguratorTrait;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\Traits\PrefillValueTrait;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\BaseMapInterface;
use exface\Core\Widgets\Parts\Maps\BaseMaps\OpenStreetMap;
use exface\Core\Interfaces\Widgets\iCanAutoloadData;
use exface\Core\Widgets\Traits\iCanAutoloadDataTrait;
use exface\Core\Interfaces\Widgets\iCanBeDragAndDropTarget;
use exface\Core\Widgets\Parts\Maps\DataShapesLayer;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * A map with support for different mapping data providers and data layers.
 * 
 *
 * ## Table of Contents
 *
 * - [Object Alias](#object-alias)
 * - [Data Setup Guide](#data-setup-guide)
 * - [General Map configuration](#general-map-configuration)
 * - [Auto Zoom](#auto-zoom)
 * 
 * ### Object Alias
 * 
 * A map can have an object alias on multiple levels. Please make sure to specify the specific object alias on each level. 
 * There is a known-issue that data with filters does not work correctly with when multiple layers contain different objects with filters. (#1629)
 * 
 * ### Data Setup Guide
 * 
 * **Data widget link to an existing table**
 * 
 * Use this setup if you want **only** load data that is **visible** inside the table you are linking (paging and filter will be adopted).
 * If you need more data please check the additional setup.
 * 
 * `"data_widget_link": "=your_table_widget_id"`
 * 
 * the content of the pop-up on the map will be the same data that is shown in the table itself.
 * 
 * **Configurator widget link to an existing table**
 * 
 * Use this setup if you want link an existing table in a way that you use the configuration but can modify what and how you want to load that data. Filter from the linked widget table will be transferred to your widget no matter your configurations (since they will be merged).
 * 
 * ```
 * "data": {
 *   "object_alias": "the.app.your_object_alias",
 *   "configurator_widget_link": "=your_table_widget_id",
 *   "paginate": false,
 *   "//": "with columns you can define what data will be shown in the pop-up content on the map. If none are defined PUI will use the attributes with a defined display position."
 *   "columns": [
 *       {
 *           "attribute_alias": "your_attribute_alias"
 *       },
 *       ...
 *       {
 *           "attribute_group_alias": "the.app.your_attribute_group_alias"
 *       }
 *   ]
 * }
 *
 * ```
 * 
 * **Define your own data**
 * 
 * Use this setup if you want a map with completely separat data from any other widget.
 * 
 * ```
 * "data": {
 *  "object_alias": "the.app.your_object_alias",
 *  "//": "only load data you need to see. Otherwise, PUI will load the whole table."
 *  "filter": [
 *      {
 *          "attribute_alias": "your_attribute_alias",
 *          "comparator": "your_comparator",
 *          "value": "your_value"
 *      }
 *  ],
 *  "columns": [
 *      {
 *          "attribute_alias": "your_attribute_alias"
 *      },
 *      {
 *          "attribute_group_alias": "the.app.your_attribute_group_alias"
 *      },
 *      {
 *          "~snippet": "the.app.your_snippet_alias"
 *      }
 *  ]
 * }
 * 
 * ```
 * 
 * ### General Map configuration
 * 
 * - Set a specific coordinate bounding box as a default center
 *      - `center_latitude` and `center_latitude_attribute_alias` give you the ability to set the latitude that is used for the original zoom when no data with auto zoom is found
 *      - `center_longitude` and `center_longitude_attribute_alias` give you the ability to set the longitude that is used for the original zoom when no data with auto zoom is found
 *      - use `center_via_gps` instead if you want the current gps data to decide the center of the map
 * 
 * ### Auto Zoom
 * 
 * Please use auto zoom as an object to configure the map zoom options. If you put that config at `map` level, it will apply these options to all layers. Alternatively you can add them to a specific `layer`.
 * See options on `auto_zoom` for more information.
 * 
 * ```
 * "auto_zoom": {
 *      "zoom_in": true,
 *      "zoom_out": true,
 *      "include_other_layers": true
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 * @summary_author Miriam Seitz
 *        
 */
class Map extends AbstractWidget implements
    iHaveToolbars, 
    iHaveButtons, 
    iHaveHeader, 
    iHaveConfigurator, 
    iFillEntireContainer,
    iCanAutoloadData,
    iCanBeDragAndDropTarget
{
    use iHaveButtonsAndToolbarsTrait;
    use PrefillValueTrait;
    use iHaveConfiguratorTrait {
        setConfiguratorWidget as setConfiguratorWidgetViaTrait;
    }
    use iCanAutoloadDataTrait;
    
    const COORDINATE_SYSTEM_PIXELS = 'pixels';
    
    const COORDINATE_SYSTEM_AUTO = 'auto';
    
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
    
    private ?float $zoomInitial = null;
    private ?float $zoomMin = null;
    private ?float $zoomMax = null;
    private ?float $zoomSnap = null;
    private ?UxonObject $autoZoomDefaults = null;
    
    private $showFullScreenButton = null;
    
    private $showGpsLocateButton = null;
    
    private $showZoomControls = null;
    
    private $showScale = true;

    private $doubleClickToZoom = true;

    private $showPopupOnClick = true;

    private $showLoadingIndicator = true;

    /**
     * @var bool
     */
    private $hide_header = null;
    
    private $coordinateSystem = self::COORDINATE_SYSTEM_AUTO;
    
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
        return $this->getBaseMaps()[$index];
    }
    
    public function getBaseMapIndex(BaseMapInterface $baseMap) : ?int
    {
        return array_search($baseMap, $this->getBaseMaps(), true);
    }
    
    /**
     * BaseMaps to show on top of the map
     *
     * @uxon-property base_maps
     * @uxon-type \exface\Core\Widgets\Parts\Maps\AbstractBaseMap[]
     * @uxon-template [{"type": ""}]
     *
     * @param UxonObject $uxon
     * @throws WidgetConfigurationError
     * @return Map
     */
    public function setBaseMaps(UxonObject $uxon) : Map
    {
        $crs = $this->getCoordinateSystem();
        if ($crs === self::COORDINATE_SYSTEM_AUTO) {
            $crs = null;
        }
        foreach ($uxon->getPropertiesAll() as $nr => $baseMapUxon) {
            $type = $baseMapUxon->getProperty('type');
            if (! $type) {
                throw new WidgetConfigurationError($this, 'No map baseMap type specified for baseMap ' . $nr);
            }
            $class = $this->getLayerClassFromType($type, self::PART_FOLDER_BASE_MAPS);
            $baseMap = new $class($this, $baseMapUxon);
            if ($crs !== null && $crs !== $baseMap->getCoordinateSystem()) {
                throw new WidgetConfigurationError('Cannot use different coordinate systems on a single map: make sure to use only `base_maps` with the same coordinate system!');
            } 
            if ($crs === null){
                $crs = $baseMap->getCoordinateSystem();
                $this->setCoordinateSystem($crs);
            }
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
            return ($layer instanceof DataMapLayerInterface);
        });
    }
    
    public function getLayer(int $index) : ?MapLayerInterface
    {
        return $this->layers[$index];
    }
    
    public function getLayerIndex(MapLayerInterface $layer) : ?int
    {
        return array_search($layer, $this->getLayers(), true);
    }
    
    /**
     * Layers to show on top of the map
     * 
     * @uxon-property layers
     * @uxon-type \exface\Core\Widgets\Parts\Maps\AbstractMapLayer[]
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
     * Returns the correct class string for that layer type.
     * 
     * specify subfolder for a quicker match.
     *
     * @param string $layerType
     * @param string|null $subfolder
     * @return string
     */
    public static function getLayerClassFromType(string $layerType, string $subfolder = null) : string
    {
        if (substr($layerType, 0, 1) === '\\') {
            $class = $layerType;
        } else {
            $class = __NAMESPACE__ . '\\Parts\\Maps\\' . ($subfolder !== null ? $subfolder . '\\' : '');
            // Try every possible path addition for a map type class
            switch ($class) {
                case class_exists($class .= $layerType):
                case class_exists($class .= 'Layer'):
                    return $class;
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
            
            // Make sure to include triggers of drop actions also, so the ActionAuthorizationPoint
            // can check if the task data really corresponds to the trigger model for the action
            if (($layer instanceof iCanBeDragAndDropTarget) && $layer->isDropTarget()) {
                if ($layer instanceof DataShapesLayer) {
                    foreach ($layer->getDropToActions() as $dropPart) {
                        yield $dropPart->getActionTrigger();
                    }
                }
            }
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
     * 
     * Show popup on click event with provided information
     * 
     * @uxon-property show_popup_on_click
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param boolean $value
     * @return Map
     *
     */
    protected function setShowPopupOnClick(bool $value) : Map
    {
        $this->showPopupOnClick = $value;
        return $this;
    }


    /**
     * 
     * @return bool
     */
    public function getShowPopupOnClick() : bool
    {
        return $this->showPopupOnClick;
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
            foreach ($layer->getWidgets() as $w) {
                $w->prefill($data_sheet);
            }
        }
        return;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param string $coordinate
     * @throws UnexpectedValueException
     */
    protected function doPrefillCoordinate(DataSheetInterface $dataSheet, string $coordinate) 
    {
        switch ($coordinate) {
            case self::COORDINATE_LAT:
                $attrAlias = $this->getCenterLatitudeAttributeAlias();
                $property = 'center_latitude';
                break;
            case self::COORDINATE_LON:
                $attrAlias = $this->getCenterLongitudeAttributeAlias();
                $property = 'center_longitude';
                break;
            default:
                throw new UnexpectedValueException('Cannot prefill map with unknown coordinate "' . $coordinate . '"!');
        }
        
        if (null !== $expr = $this->getPrefillExpression($dataSheet, $this->getMetaObject(), $attrAlias)) {
            $this->doPrefillForExpression(
                $dataSheet, 
                $expr, 
                $property, 
                function($value) use ($coordinate) {
                    if ($coordinate === self::COORDINATE_LAT) {
                        $this->setCenterLatitude($value);
                    } else {
                        $this->setCenterLongitude($value);
                    }
                }
            );
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
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        
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
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
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
    public function getCenterLatitudeAttributeAlias() : ?string
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
     * @param float|string|NULL $value
     * @return Map
     */
    public function setCenterLatitude($value) : Map
    {
        $this->centerLatitude = NumberDataType::cast($value);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getCenterLongitudeAttributeAlias() : ?string
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
     * @param float|string|NULL $value
     * @return Map
     */
    public function setCenterLongitude($value) : Map
    {
        $this->centerLongitude = NumberDataType::cast($value);
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
        return $this->zoomInitial;
    }

    public function getDoubleClickToZoom() : ?bool
    {
        return $this->doubleClickToZoom;
    }

    /**
     * Enable double click to zoom feature on map
     * 
     * @uxon-property double_click_to_zoom
     * @uxon-type boolean
     * 
     * @param boolean $value
     * @return Map
     */
    protected function setDoubleClickToZoom(bool $value) : Map
    {
        $this->doubleClickToZoom = $value;
        return $this;
    }
    
    /**
     * The initial zoom factor
     * 
     * The value depends on the rendering library used by the facade, however powers of 2
     * are very common: 1 for complete zoom out (whole earth or even more), 2 double zoom, 3
     * for 4x zoom, etc.
     * 
     * Many map layers support `auto_zoom`, which will zoom automatically when the layer is loaded and make sure,
     * all layer items are visible. You can set the defaults for `auto_zoom` on map level too. However, `zoom_initial`
     * will be applied right when the map is loaded - even without any layers.
     * 
     * @uxon-property zoom_initial
     * @uxon-type number
     * 
     * @param float $value
     * @return Map
     */
    public function setZoomInitial(float $value) : Map
    {
        $this->zoomInitial = $value;
        return $this;
    }

    /**
     * @deprecated use zoom_initial instead
     * 
     * @param float $value
     * @return $this
     */
    protected function setZoom(float $value) : Map
    {
        return $this->setZoomInitial($value);
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getZoomMin() : ?float
    {
        return $this->zoomMin;
    }
    
    /**
     * The minimum zoom value for this map
     * 
     * @uxon-property zoom_min
     * @uxon-type integer
     * 
     * @param float $value
     * @return Map
     */
    public function setZoomMin(float $value) : Map
    {
        $this->zoomMin = $value;
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getZoomMax() : ?float
    {
        return $this->zoomMax;
    }
    
    /**
     * The maximum zoom value for this map
     * 
     * @uxon-property zoom_max
     * @uxon-type number
     * 
     * @param float $value
     * @return Map
     */
    public function setZoomMax(float $value) : Map
    {
        $this->zoomMax = $value;
        return $this;
    }
    
    public function getZoomStep() : ?float
    {
        return $this->zoomSnap;
    }

    /**
     * Factor to zoom at a time when pressing the zoom controls or using mouse wheel.
     * 
     * Use fractional steps like `0.25` or `0.1` for a smooth zoom. 
     * 
     * By default, the `zoom_step` is 1. Thus, valid zoom levels are `0`, `1`, `2`, `3`, etc. If you set the value 
     * of zoomSnap to 0.5, the valid zoom levels of the map will be `0`, `0.5`, `1`, `1.5`, `2`, and so on. If you set 
     * a value of 0.1, the valid zoom levels of the map will be `0`, `0.1`, `0.2`, `0.3`, `0.4`, and so on.
     * 
     * @uxon-property zoom_step
     * @uxon-type float
     * @uxon-default 1
     * 
     * @param float $value
     * @return $this
     */
    public function setZoomStep(float $value) : Map
    {
        $this->zoomSnap = $value;
        return $this;
    }

    /**
     * Default setting for auto_zoom for all layers, that support it
     *
     * @uxon-property auto_zoom
     * @uxon-type \exface\Core\Widgets\Parts\Maps\AutoZoom
     * @uxon-template {"zoom_in": false, "zoom_out": true}
     *
     * @param UxonObject $uxon
     * @return $this
     */
    protected function setAutoZoom(UxonObject $uxon) : Map
    {
        $this->autoZoomDefaults = $uxon;
        return $this;
    }

    /**
     * @return UxonObject|null
     */
    public function getAutoZoomDefaults() : ?UxonObject
    {
        if ($this->autoZoomDefaults === null && count($this->getDataLayers())) {
            $this->autoZoomDefaults = new UxonObject([
                'zoom_in' => true
            ]);
        }
        return $this->autoZoomDefaults;
    }
    
    /**
     * 
     * @return bool
     */
    public function getShowFullScreenButton() : bool
    {
        if ($this->showFullScreenButton !== null) {
            return $this->showFullScreenButton;
        }
        
        // The header and caption already contains the fullscreen button.
        if ($this->getHideCaption() && $this->getHideHeader()) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Set to FALSE to disallow switching to full-screen view.
     * 
     * @uxon-property show_full_screen_button
     * @uxon-type boolean
     * @uxon-default true
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
        if ($this->showGpsLocateButton === null) {
            foreach ($this->getBaseMaps() as $map) {
                if ($map->getCoordinateSystem() === self::COORDINATE_SYSTEM_PIXELS) {
                    return false;
                }
            }
        }
        return $this->showGpsLocateButton ?? true;
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
     * @return bool
     * 
     */
    public function getShowZoomControls() : bool
    {
        if ($this->showZoomControls === null) {
            foreach ($this->getBaseMaps() as $map) {
                if ($map->getCoordinateSystem() === self::COORDINATE_SYSTEM_PIXELS) {
                    return false;
                }
            }
        }
        return $this->showZoomControls ?? true;
    }
    
    /**
     * Set to FALSE to hide the zoom and home controls from the map.
     * - zoom controls are the "+" and "-" buttons to zoom in and out.
     * - home control is the button to reset the view to the initial center and zoom level.
     * 
     * @uxon-property show_zoom_controls
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return Map
     */
    public function setShowZoomControls(bool $value) : Map
    {        
        $this->showZoomControls = $value;
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

    /**
     * 
     * @return bool
     */
    public function getShowLoadingIndicator() : bool
    {
        return $this->showLoadingIndicator;
    }
    
    /**
     * Set to FALSE to hide the loading overlay while calling the data.
     * 
     * @uxon-property show_loading_indicator
     * @uxon-type bool
     * @uxon-default true
     * 
     * @param bool $value
     * @return Map
     */
    public function setShowLoadingIndicator(bool $value) : Map
    {
        $this->showLoadingIndicator = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        $result = parent::importUxonObject($uxon);
        
        if (empty($this->baseMaps)) {
            $this->baseMaps[] = new OpenStreetMap($this);
        }
        
        return $result;
    }
    
    /**
     * 
     * @return string
     */
    public function getCoordinateSystem() : string
    {
        return $this->coordinateSystem;
    }
    
    /**
     * 
     * @param string $value
     * @return Map
     */
    protected function setCoordinateSystem(string $value) : Map
    {
        $this->coordinateSystem = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see iCanBeDragAndDropTarget::isDropTarget()
     */
    public function isDropTarget(): bool
    {
        foreach ($this->getLayers() as $layer) {
            if (($layer instanceof iCanBeDragAndDropTarget) && $layer->isDropTarget() === true) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * {@inheritDoc}
     * @see AbstractWidget::getMetaObjectsEffectingThisWidget()
     */
    public function getMetaObjectsEffectingThisWidget() : array
    {
        // Main object
        $objs = parent::getMetaObjectsEffectingThisWidget();
        // Widgets of each layer
        foreach ($this->getLayers() as $layer) {
            foreach ($layer->getWidgets() as $child) {
                $objs = array_merge($objs, $child->getMetaObjectsEffectingThisWidget());
            }
        }
        return array_unique($objs);
    }
}