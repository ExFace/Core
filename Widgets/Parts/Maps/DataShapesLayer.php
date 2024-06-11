<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Widgets\Parts\Maps\Interfaces\EditableMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\ColoredDataMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\ColoredLayerTrait;
use exface\Core\Widgets\Parts\Maps\Traits\ValueLabeledLayerTrait;
use exface\Core\Widgets\Parts\Maps\Interfaces\ValueLabeledMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\CustomProjectionMapLayerInterface;
use exface\Core\Widgets\Parts\DragAndDrop\DropToAction;
use exface\Core\Interfaces\Widgets\iCanBeDragAndDropTarget;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonWidgetLinkMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\CustomProjectionLayerTrait;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class DataShapesLayer extends AbstractDataLayer 
    implements
    GeoJsonMapLayerInterface,
    GeoJsonWidgetLinkMapLayerInterface,
    ColoredDataMapLayerInterface,
    ValueLabeledMapLayerInterface,
    EditableMapLayerInterface,
    CustomProjectionMapLayerInterface,
    iCanBeDragAndDropTarget
{
    const VALUE_POSITION_LEFT = 'left';
    
    const VALUE_POSITION_RGHT = 'right';
    
    const VALUE_POSITION_TOP = 'top';
    
    const VALUE_POSITION_BOTTOM = 'bottom';
    
    const VALUE_POSITION_CENTER = 'center';
    
    const VALUE_POSITION_TOOLTIP = 'tooltip';
    
    use ColoredLayerTrait;
    
    use ValueLabeledLayerTrait;
    
    use CustomProjectionLayerTrait;
    
    private $shapeAttributeAlias = null;
    
    private $shapeColumn = null;
    
    private $shapeLink = null;
    
    private $addShapes = false;
    
    private $addShapesMax = null;
    
    private $changeShapes = false;
    
    private $lineWeight = null;
    
    private $opacity = null;
    
    private $valuePosition = self::VALUE_POSITION_TOOLTIP;
    
    private $dropToActions = [];
    
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
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonWidgetLinkMapLayerInterface::getShapesWidgetLink()
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
        return $this->hasEditByAddingItems() || $this->hasEditByChangingItems();
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
     * Set to TRUE to allow adding shapes
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
        return $this->addShapesMax;
    }
    
    /**
     *
     * @return bool
     */
    public function hasEditByChangingItems() : bool
    {
        return $this->changeShapes;
    }
    
    /**
     * Set to TRUE to allow changin or moving shapes
     * 
     * @uxon-property edit_by_changing_items
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return MapLayerInterface
     */
    public function setEditByChangingItems(bool $value) : MapLayerInterface
    {
        $this->changeShapes = $value;
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
    
    /**
     *
     * @return string
     */
    public function getValuePosition() : string
    {
        return $this->valuePosition;
    }
    
    /**
     * Where to show the value relatively to the center of the shape - center (default), right, left, top, bottom or center
     *
     * @uxon-property value_position
     * @uxon-type [tooltip,right,left,top,bottom,center]
     * @uxon-default tooltip
     *
     * @param string $value
     * @return DataPointsLayer
     */
    public function setValuePosition(string $value) : DataShapesLayer
    {
        $this->valuePosition = $value;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    public function getDropToActions() : array
    {
        return $this->dropToActions;
    }
    
    /**
     * Actions to be performed when something is dropped on this layer
     * 
     * @uxon-property drop_to_action
     * @uxon-type \exface\Core\Widgets\Parts\DragAndDrop\DropToAction[]
     * @uxon-template [{"object_alias": "", "action":{"alias": ""}}]
     * 
     * @param UxonObject $arrayOfWidgetParts
     * @return DataShapesLayer
     */
    public function setDropToAction(UxonObject $arrayOfWidgetParts) : DataShapesLayer
    {
        foreach ($arrayOfWidgetParts->getPropertiesAll() as $partUxon) {
            $this->dropToActions[] = new DropToAction($this->getMap(), $partUxon);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see iCanBeDragAndDropTarget::isDropTarget()
     */
    public function isDropTarget(): bool
    {
        return ! empty($this->dropToActions);
    }
}