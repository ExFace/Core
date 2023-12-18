<?php
namespace exface\Core\Widgets\Parts\Maps\Traits;

use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Widgets\Parts\Maps\Interfaces\MapLayerInterface;
use exface\Core\Widgets\DataColumn;

/**
 * This trait adds properties to display a value next to each item in the layer
 * 
 * - `value_attribute_alias`
 * 
 * @author Andrej Kabachnik
 *
 */
trait ValueLabeledLayerTrait
{
    private $valueAttributeAlias = null;
    
    private $valueColumn = null;
    
    /**
     *
     * @return string|NULL
     */
    public function getValueAttributeAlias() : ?string
    {
        return $this->valueAttributeAlias;
    }
    
    /**
     * Alias of the attribtue containing the data to show inside each marker/point or next to it (typically a number or a short string)
     *
     * @uxon-property value_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $value
     * @return MapLayerInterface
     */
    public function setValueAttributeAlias(string $value) : MapLayerInterface
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
     * @return DataColumn|NULL
     */
    public function getValueColumn() : ?DataColumn
    {
        return $this->valueColumn;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Parts\Maps\AbstractDataLayer::initDataWidget()
     */
    protected function initDataWidgetValue(iShowData $widget) : iShowData
    {
        if (null !== $alias = $this->getValueAttributeAlias()) {
            if (! $col = $widget->getColumnByAttributeAlias($alias)) {
                $col = $widget->createColumnFromUxon(new UxonObject([
                    'attribute_alias' => $alias,
                    'visibility' => WidgetVisibilityDataType::PROMOTED
                ]));
                $widget->addColumn($col, 0);
            }
            $this->valueColumn = $col;
        }
        
        return $widget;
    }
}