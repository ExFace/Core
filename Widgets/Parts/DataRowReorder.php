<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Widgets\Traits\DataWidgetPartTrait;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\SortingDirectionsDataType;

/**
 * This widget part is used to reorder rows in a DataTable
 * 
 * Example:
 * 
 * ```json
 * {
 *  "widget_type": "DataTable",
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
        $uxon = new UxonObject([
            'order_index_attribute_alias' => $this->getOrderIndexAttributeAlias(),
            'direction' => $this->direction
        ]);
        
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
     * @throws UnexpectedValueException
     * @return \exface\Core\Widgets\Parts\DataRowReorder
     */
    public function setOrderDirection($value)
    {
        if (strtoupper($value) == SortingDirectionsDataType::ASC) {
            $this->direction = SortingDirectionsDataType::ASC;
        } elseif (strtoupper($value) == SortingDirectionsDataType::DESC) {
            $this->direction = SortingDirectionsDataType::DESC;
        } else {
            throw new UnexpectedValueException('Invalid sort direction "' . $value . '" for a date row reorder only DESC or ASC are allowed!', '6T5V9KS');
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