<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Parts\Maps\Interfaces\EditableMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\ColoredDataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\ColoredLayerTrait;
use exface\Core\Widgets\Parts\Maps\Traits\ValueLabeledLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\ValueLabeledMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonMapLayerInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class DataShapesLayer extends AbstractDataLayer 
    implements
    GeoJsonMapLayerInterface,
    ColoredDataMapLayerInterface,
    ValueLabeledMapLayerInterface,
    EditableMapLayerInterface
{
    
    use ColoredLayerTrait;
    
    use ValueLabeledLayerTrait;
    
    private $shapeAttributeAlias = null;
    
    private $shapeColumn = null;
    
    private $shapeLink = null;
    
    private $addShapes = false;
    
    private $addMarkerMax = null;
    
    private $draggable = false;
    
    private $lineWeight = null;
    
    private $opacity = null;
    
    /**
     *
     * @return string
     */
    public function getShapesAttributeAlias() : string
    {
        return $this->shapeAttributeAlias;
    }
    
    /**
     * Alias of the attribtue that will contain the shape of a marker
     *
     * @uxon-property shapes_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setShapesAttributeAlias(string $value) : MapLayerInterface
    {
        $this->shapeAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return DataColumn
     */
    public function getShapesColumn() : DataColumn
    {
        return $this->shapeColumn;
    }
    
    /**
     *
     * @return WidgetLinkInterface|NULL
     */
    public function getShapesWidgetLink() : ?WidgetLinkInterface
    {
        return $this->shapeLink;
    }
    
    /**
     * The id of the widget to sync the shape to (e.g. InputHidden)
     *
     * Only works in conjuction with shape_widget_link!
     *
     * @uxon-property shapes_widget_link
     * @uxon-type uxon:$..id
     *
     * @param string $value
     * @return MapLayerInterface
     */
    protected function setShapesWidgetLink(string $value) : MapLayerInterface
    {
        $this->shapeLink = WidgetLinkFactory::createFromWidget($this->getMap(), $value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function createDataWidget(UxonObject $uxon) : iShowData
    {
        $widget = parent::createDataWidget($uxon);
        
        if ($uxon->hasProperty('columns')) {
            $widget->setColumnsAutoAddDefaultDisplayAttributes(false);
        }
        
        return $widget;
    }
    
    /**
     *
     * @return bool
     */
    public function isEditable() : bool
    {
        return $this->hasEditByAddingItems() || $this->hasEditByMovingItems();
    }
    
    /**
     *
     * @return bool
     */
    public function hasEditByAddingItems() : bool
    {
        return $this->addShapes;
    }
    
    /**
     * Set to TRUE to allow adding markers
     *
     * @uxon-property edit_by_adding_items
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return MapLayerInterface
     */
    public function setEditByAddingItems(bool $value) : MapLayerInterface
    {
        $this->addShapes = $value;
        return $this;
    }
    
    /**
     *
     * @return int|NULL
     */
    public function hasEditByAddingItemsMax() : ?int
    {
        if ($this->hasEditByAddingItems() === false) {
            return 0;
        }
        if ($link = $this->getLatitudeWidgetLink()) {
            $w = $link->getTargetWidget();
            if (($w instanceof iSupportMultiSelect) && $w->getMultiSelect() === true) {
                return null;
            } else {
                return 1;
            }
        }
        return null;
    }
    
    /**
     *
     * @return bool
     */
    public function hasEditByMovingItems() : bool
    {
        return $this->draggable;
    }
    
    /**
     * Set to TRUE to allow moving markers
     *
     * @uxon-property edit_by_moving_items
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return MapLayerInterface
     */
    public function setEditByMovingItems(bool $value) : MapLayerInterface
    {
        $this->draggable = $value;
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getLineWeight() : ?float
    {
        return $this->lineWeight;
    }
    
    /**
     * Weight (thikness) of the lines.
     *
     * The accepted values depend on the facade used. However, pixels are very common:
     * try `5` for 5 pixels.
     *
     * @uxon-property line_weight
     * @uxon-type number
     *
     * @param float $value
     * @return GeoJSONLayer
     */
    public function setLineWeight(float $value) : GeoJSONLayer
    {
        $this->lineWeight = NumberDataType::cast($value);
        return $this;
    }
    
    /**
     * 
     * @return float|NULL
     */
    public function getOpacity() : ?float
    {
        return $this->opacity;
    }
    
    /**
     * Opacity of the layer: `1` for not transparent to `0` for invisible.
     *
     * @uxon-property opacity
     * @uxon-type number
     *
     * @param float $value
     * @return GeoJSONLayer
     */
    public function setOpacity(float $value) : GeoJSONLayer
    {
        $this->opacity = NumberDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $widget = parent::initDataWidget($widget);
        if ($alias = $this->getShapesAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($alias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->getShapesAttributeAlias(),
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->shapeColumn = $col;
        }
        
        $widget = $this->initDataWidgetColor($widget);
        $widget = $this->initDataWidgetValue($widget);
        
        return $widget;
    }
}