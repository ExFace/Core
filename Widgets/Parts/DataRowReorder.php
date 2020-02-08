<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * This widget part is used to reorder rows in a `DataTable` or `DataTree`.
 * 
 * Adds the possibility to reorder rows in a data widget and save their order. Depending
 * on the facade, the reordering may work differently: e.g. via drag&drop or up/down
 * buttons. Changing the order will only update the affected attributes - i.e. the
 * `order_index_attribute_alias` here and eventually the parent in a tree widget.
 * 
 * ## Example:
 * 
 * ```json
 * {
 *  "widget_type": "DataTree",
 *  "row_reorder": {
 *      "order_index_attribute_alias": "MY_ATTRIBUTE",
 *      "direction": "ASC"
 *  }
 * }
 * 
 * ```
 * 
 * @method DataTable getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class DataRowReorder implements WidgetPartInterface
{
    use DataWidgetPartTrait;
    
    private $order_index_attribute_alias;
    
    private $direction = SortingDirectionsDataType::ASC;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('order_index_attribute_alias', $this->getOrderIndexAttributeAlias());
        $uxon->setProperty('direction', $this->direction);
        
        return $uxon;
    }
    
    /**
     * 
     * @return string
     */
    public function getOrderIndexAttributeAlias() : string
    {
        return $this->order_index_attribute_alias;
    }
    
    /**
     * Specifies the attribute to reorder rows by.
     *
     * @uxon-property order_index_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $alias
     * @return \exface\Core\Widgets\Parts\DataRowReorder
     */
    public function setOrderIndexAttributeAlias(string $alias) : DataRowReorder
    {
        $this->order_index_attribute_alias = $alias;
        return $this;
    }
    
    /**
     *
     * @uxon-property order_direction
     * @uxon-type [asc,desc]
     *
     * @param string $value
     * @throws InvalidArgumentException
     * @return \exface\Core\Widgets\Parts\DataRowReorder
     */
    public function setOrderDirection($value)
    {
        if (strtoupper($value) == SortingDirectionsDataType::ASC) {
            $this->direction = SortingDirectionsDataType::ASC;
        } elseif (strtoupper($value) == SortingDirectionsDataType::DESC) {
            $this->direction = SortingDirectionsDataType::DESC;
        } else {
            throw new InvalidArgumentException('Invalid sort direction "' . $value . '" for a date row reorder only DESC or ASC are allowed!', '6T5V9KS');
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getOrderDirection() : string
    {
        return $this->direction;
    }
}