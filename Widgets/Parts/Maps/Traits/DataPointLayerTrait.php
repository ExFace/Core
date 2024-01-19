<?php
namespace exface\Core\Widgets\Parts\Maps\Traits;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
trait DataPointLayerTrait
{    
    private $latitudeAttributeAlias = null;
    
    private $latitudeColumn = null;
    
    private $latitudeLink = null;
    
    private $longitudeAttributeAlias = null;
    
    private $longitudeColumn = null;
    
    private $shapeLink = null;
    
    private $tooltipAttribtueAlias = null;
    
    private $tooltipColumn = null;
    
    private $addMarkers = false;
    
    private $addMarkerMax = null;
    
    private $draggable = false;
    
    /**
     * 
     * @return string
     */
    public function getLatitudeAttributeAlias() : string
    {
        return $this->latitudeAttributeAlias;
    }
    
    /**
     * Alias of the attribtue that will contain the latitude of a marker
     * 
     * @uxon-property latitude_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return MapLayerInterface
     */
    public function setLatitudeAttributeAlias(string $value) : MapLayerInterface
    {
        $this->latitudeAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getLatitudeColumn() : DataColumn
    {
        return $this->latitudeColumn;
    }
    
    /**
     * 
     * @return WidgetLinkInterface|NULL
     */
    public function getLatitudeWidgetLink() : ?WidgetLinkInterface
    {
        return $this->latitudeLink;
    }
    
    /**
     * The id of the widget to sync the latitude to (e.g. InputHidden)
     * 
     * Only works in conjuction with longitude_widget_link!
     *
     * @uxon-property latitude_widget_link
     * @uxon-type uxon:$..id
     *
     * @param string $value
     * @return MapLayerInterface
     */
    protected function setLatitudeWidgetLink(string $value) : MapLayerInterface
    {
        $this->latitudeLink = WidgetLinkFactory::createFromWidget($this->getMap(), $value);
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getLongitudeAttributeAlias() : string
    {
        return $this->longitudeAttributeAlias;
    }
    
    /**
     * Alias of the attribtue that will contain the longitude of a marker
     *
     * @uxon-property longitude_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setLongitudeAttributeAlias(string $value) : MapLayerInterface
    {
        $this->longitudeAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getLongitudeColumn() : DataColumn
    {
        return $this->longitudeColumn;
    }

    /**
     * 
     * @return WidgetLinkInterface|NULL
     */
    public function getLongitudeWidgetLink() : ?WidgetLinkInterface
    {
        return $this->shapeLink;
    }
    
    /**
     * The id of the widget to sync the longitude to (e.g. InputHidden)
     * 
     * Only works in conjuction with longitude_widget_link!
     * 
     * @uxon-property longitude_widget_link
     * @uxon-type uxon:$..id
     * 
     * @param string $value
     * @return MapLayerInterface
     */
    protected function setLongitudeWidgetLink(string $value) : MapLayerInterface
    {
        $this->shapeLink = WidgetLinkFactory::createFromWidget($this->getMap(), $value);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getTooltipAttributeAlias() : ?string
    {
        return $this->tooltipAttribtueAlias;
    }
    
    /**
     * Alias of the attribtue containing the data to show in the tooltip of a marker
     *
     * @uxon-property tooltip_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setTooltipAttributeAlias(string $value) : MapLayerInterface
    {
        $this->tooltipAttribtueAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasTooltip() : bool
    {
        return $this->getTooltipAttributeAlias() !== null;
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getTooltipColumn() : ?DataColumn
    {
        return $this->tooltipColumn;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        $widget = parent::initDataWidget($widget);
        if ($this->getLatitudeAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($this->getLatitudeAttributeAlias())) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->getLatitudeAttributeAlias(),
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->latitudeColumn = $col;
        }
        if ($this->getLongitudeAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($this->getLongitudeAttributeAlias())) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->getLongitudeAttributeAlias(),
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->longitudeColumn = $col;
        }
        if ($this->getTooltipAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($this->getTooltipAttributeAlias())) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->getTooltipAttributeAlias(),
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->tooltipColumn = $col;
        }
        
        return $widget;
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
        return $this->addMarkers;
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
        $this->addMarkers = $value;
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
}