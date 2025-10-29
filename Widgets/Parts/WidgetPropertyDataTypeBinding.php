<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Widgets\iSupportAggregators;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyDataTypeBindingInterface;

/**
 * Allows to bind a widget property to a data type either directly or through an attribute.
 * 
 * @author Andrej Kabachnik
 * 
 * TODO probably need to separate this from the value-binding in WidgetPropertyBinding.
 * After all, the data type binding does not have a value and it actually is just there to allow
 * a configurable linke to a data type from a widget
 */
class WidgetPropertyDataTypeBinding extends WidgetPropertyBinding implements WidgetPropertyDataTypeBindingInterface
{    
    const BINDING_TYPE_DATA_TYPE = 'datatype';
    
    private $dataTypeAlias = null;

    /**
     * Bind this property to a metamodel datatype.
     *
     * @uxon-property data_type_alias
     * @uxon-type metamodel:datatype
     *
     * @param string $alias
     * @return WidgetPropertyBindingInterface
     */
    protected function setDataTypeAlias(string $alias) : WidgetPropertyBindingInterface
    {
        $this->dataTypeAlias = $alias;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getBindingType() : string
    {
        if ($this->dataTypeAlias !== null) {
            return self::BINDING_TYPE_DATA_TYPE;
        }
        
        return parent::getBindingType();
    }

    /**
     *
     * {@inheritDoc}
     * @see WidgetPropertyBindingInterface::isBoundToDataColumn
     */
    public function isBoundToDataType() : bool
    {
        return $this->getBindingType() === self::BINDING_TYPE_DATA_TYPE;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see WidgetPropertyBindingInterface::isEmpty
     */
    public function isEmpty() : bool
    {
        return $this->dataTypeAlias !== null ? false : parent::isEmpty();
    }

    /**
     * 
     * {@inheritDoc}
     * @see WidgetPropertyBindingInterface::getDataType
     */
    public function getDataType() : ?DataTypeInterface
    {
        switch ($this->getBindingType()) {
            case self::BINDING_TYPE_DATA_TYPE:
                return DataTypeFactory::createFromString($this->getWorkbench(), $this->dataTypeAlias);
            case self::BINDING_TYPE_ATTRIBUTE:
                return $this->getAttribute()->getDataType();
            // TODO what about data columns?
        } 
        return null;
    }
}