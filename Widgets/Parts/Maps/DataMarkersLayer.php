<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\DataColumn;

/**
 *
 * @author Andrej Kabachnik
 *
 */
class DataMarkersLayer extends AbstractDataLayer
{
    private $latitudeAttributeAlias = null;
    
    private $latitudeColumn = null;
    
    private $longitudeAttributeAlias = null;
    
    private $longitudeColumn = null;
    
    private $valueAttributeAlias = null;
    
    private $valueColumn = null;
    
    private $tooltipAttribtueAlias = null;
    
    private $tooltipColumn = null;
    
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
     * @return DataMarkersLayer
     */
    public function setLatitudeAttributeAlias(string $value) : DataMarkersLayer
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
     * @return DataMarkersLayer
     */
    public function setLongitudeAttributeAlias(string $value) : DataMarkersLayer
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
     * @return string|NULL
     */
    public function getValueAttributeAlias() : ?string
    {
        return $this->valueAttributeAlias;
    }
    
    /**
     * Alias of the attribtue containing the data to show inside the marker (typically a number)
     *
     * @uxon-property value_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return DataMarkersLayer
     */
    public function setValueAttributeAlias(string $value) : DataMarkersLayer
    {
        $this->valueAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasValue() : bool
    {
        return $this->getValueAttributeAlias() !== null;
    }
    
    /**
     * 
     * @return DataColumn|NULL
     */
    public function getValueColumn() : ?DataColumn
    {
        return $this->tooltipColumn;
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
     * @return DataMarkersLayer
     */
    public function setTooltipAttributeAlias(string $value) : DataMarkersLayer
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
        if ($this->getValueAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($this->getValueAttributeAlias())) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $this->getValueAttributeAlias(),
                    'hidden' => true
                ]));
                $widget->addColumn($col);
            }
            $this->valueColumn = $col;
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
     * @see \exface\Core\Interfaces\Widgets\iHaveCaption::getCaption()
     */
    public function getCaption() : ?string
    {
        $caption = parent::getCaption();
        if (! $this->getHideCaption()) {
            if ($caption === null) {
                if ($this->hasValue()) {
                    $caption = $this->getValueColumn()->getCaption();
                }
                if (! $caption && $this->hasTooltip()) {
                    $caption = $this->getTooltipColumn()->getCaption();
                }
                if (! $caption) {
                    $caption = $this->getDataWidget()->getMetaObject()->getName();
                }
            }
        }
        return $caption;
    }
}