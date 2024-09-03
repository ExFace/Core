<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Interface for widget property bindings
 * 
 * @author Andrej Kabachnik
 *        
 */
interface WidgetPropertyBindingInterface extends WidgetPartInterface, iCanBeBoundToAttribute, iCanBeBoundToDataColumn
{    
    /**
     * Returns TRUE if this binding points to a attribute.
     * 
     * You can get this attribute via `getAttributeAlias()` and `getAttribute()`.
     * 
     * @return bool
     */
    public function isBoundToAttribute() : bool;

    /**
     * Returns TRUE if the binding points to data of any kind (either an attribute or a data column)
     * 
     * @return bool
     */
    public function isBoundToDataColumn() : bool;
    
    /**
     * Returns the full attribute alias if the binding points to an attribute (including relation path and aggregator if present)
     * 
     * @return string|null
     */
    public function getAttributeAlias() : ?string;

    /**
     * Returns the name of the widget property bound
     * 
     * @return string
     */
    public function getPropertyName() : string;
    
    /**
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $dataSheet
     * @return DataSheetInterface
     */
    public function prepareDataSheetToRead(DataSheetInterface $dataSheet);
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $dataSheet);
    
    /**
     * 
     * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $dataSheet
     * @return void
     */
    public function prefill(DataSheetInterface $dataSheet);
    
    /**
     * 
     * @return string|null
     */
    public function getDataColumnName();
    
    /**
     *
     * @return MetaAttributeInterface|null
     */
    public function getAttribute() : ?MetaAttributeInterface;
    
    /**
     * 
     * @param string|null $value
     * @return \exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface
     */
    public function setValue($value) : WidgetPropertyBindingInterface;
    
    /**
     * 
     * @return string|null
     */
    public function getValue() : ?string;
    
    /**
     * 
     * @return ExpressionInterface|null
     */
    public function getValueExpression() : ?ExpressionInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasValue() : bool;
    
    /**
     * 
     * @return bool
     */
    public function isEmpty() : bool;
    
    /**
     * 
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface;

    /**
     * 
     * @return DataTypeInterface|null
     */
    public function getDataType() : ?DataTypeInterface;
    
    /**
     * 
     * @return \exface\Core\Interfaces\Model\AggregatorInterface|null
     */
    public function getAggregator(): ?AggregatorInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasAggregator() : bool;
}