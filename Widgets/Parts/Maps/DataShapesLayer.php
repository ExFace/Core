<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iCanBlink;
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
use exface\Core\DataTypes\DateDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Interfaces\CustomProjectionMapLayerInterface;
use exface\Core\Widgets\Parts\DragAndDrop\DropToAction;
use exface\Core\Interfaces\Widgets\iCanBeDragAndDropTarget;
use exface\Core\Widgets\Parts\Maps\Interfaces\GeoJsonWidgetLinkMapLayerInterface;
use exface\Core\Widgets\Parts\Maps\Traits\CustomProjectionLayerTrait;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Widgets\iHaveColorWithOutline;

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
    iCanBeDragAndDropTarget,
    iHaveColorWithOutline,
    iCanBlink
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

    private $isBlinking = false;
    
    private $valuePosition = self::VALUE_POSITION_TOOLTIP;
    
    private $dropToActions = [];
    
    private $colorOutlineScale = null;
    
    private $colorOutlineAttributeAlias = null;
    
    private $colorOutlineColumn = null;
    
    private $blinkingAttributeAlias = null;
    
    private $blinkingColumn = null;
    
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
    public function setLineWeight(float $value) : DataShapesLayer
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
    public function setOpacity(float $value) : DataShapesLayer
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
        if (null !== $alias = $this->getShapesAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($alias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $alias,
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->shapeColumn = $col;
        }
        
        if (null !== $alias = $this->getBlinkingAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($alias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $alias,
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->blinkingColumn = $col;
        }
        
        $widget = $this->initDataWidgetColor($widget);
        $widget = $this->initDataWidgetColorOutline($widget);
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
    
    /**
     * 
     * @return array
     */
    public function getColorOutlineScale() : array
    {
        return $this->colorOutlineScale ?? [];
    }
    
    /**
     * 
     * @return bool
     */
    public function hasColorOutlineScale() : bool
    {
        return $this->colorOutlineScale !== null;
    }
    
    /**
     * Specify a custom color scale for the outer border of the shape.
     *
     * The color map must be an object with values as keys and CSS color codes as values.
     * The color code will be applied to all values between it's value and the previous
     * one. In the below example, all values <= 10 will be red, values > 10 and <= 20
     * will be colored yellow, those > 20 and <= 99 will have no special color and values
     * starting with 100 (actually > 99) will be green.
     *
     * ```
     * {
     *  "10": "red",
     *  "20": "yellow",
     *  "99" : "",
     *  "100": "green"
     * }
     *
     * ```
     *
     * @uxon-property color_outline_scale
     * @uxon-type color[]
     * @uxon-template {"10": "red", "20": "yellow", "99": "", "100": "green"}
     *
     * @param UxonObject $value
     * @return MapLayerInterface
     */
    public function setColorOutlineScale(UxonObject $value) : MapLayerInterface
    {
        $this->colorOutlineScale = $value->toArray();
        ksort($this->colorOutlineScale);
        return $this;
    }


    /**
     * Boolean flag to enable blinking of the shape
     *
     * @uxon-property is_blinking
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setIsBlinking(bool $value) : MapLayerInterface
    {
        $this->isBlinking = $value;
        return $this;
    }


    public function getIsBlinking() : bool
    {
        return $this->isBlinking;
    }

    public function isColorOutlineScaleRangeBased(DataTypeInterface $dataType = null) : bool
    {
        if (! $this->hasColor()) {
            return false;
        }
        $dataType = $dataType ?? $this->getColorColumn()->getDataType();
        switch (true) {
            case $dataType instanceof NumberDataType:
            case $dataType instanceof DateDataType:
                return true;
        }
        
        return false;
    }
    
    /**
    *
    * @return string|NULL
    */
    public function getColorOutlineAttributeAlias() : ?string
    {
        return $this->colorOutlineAttributeAlias;
    }
    
    /**
     * Alias of the attribtue containing the exact color value or base value for the `color_scale`
     *
     * @uxon-property color_outline_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setColorOutlineAttributeAlias(string $value) : MapLayerInterface
    {
        $this->colorOutlineAttributeAlias = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function hasColorOutline() : bool
    {
        return $this->getColorOutlineAttributeAlias() !== null;
    }
    
    /**
     *
     * @return DataColumn|NULL
     */
    public function getColorOutlineColumn() : ?DataColumn
    {
        return $this->colorOutlineColumn;
    }
    
    /**
     *
     * @param iShowData $widget
     * @return iShowData
     */
    protected function initDataWidgetColorOutline(iShowData $widget) : iShowData
    {
        if (null !== $alias = $this->getColorOutlineAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($alias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $alias,
                    'visibility' => WidgetVisibilityDataType::HIDDEN
                ]));
                $widget->addColumn($col, 0);
            }
            $this->colorOutlineColumn = $col;
        }
        
        return $widget;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getBlinkingAttributeAlias() : ?string
    {
        return $this->blinkingAttributeAlias;
    }
    
    /**
     * Alias of a boolean attribtue that must be TRUE to make the shape blink
     * 
     * ## Examples
     * 
     * Blink when a boolean attribute ist TRUE.
     * ```
     *  {
     *      "type": "DataShapes",
     *      "blinking_attribute": "Status__ErrorFlag"
     *  }
     *  
     * ```
     * 
     * Use a formula to calculate a boolean value from others
     * ```
     *  {
     *      "type": "DataShapes",
     *      "blinking_attribute": "=Calc(Status >= 20 AND Status < 30)"
     *  }
     *  
     * ```
     *
     * @uxon-property blinking_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setBlinkingAttribute(string $value) : MapLayerInterface
    {
        $this->blinkingAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getBlinkingFlagColumn() : ?DataColumn
    {
        return $this->blinkingColumn;
    }

    /**
     * @inheritDoc
     */
    public function hasProjectionDefinition(): bool {
        return false;
    }
}