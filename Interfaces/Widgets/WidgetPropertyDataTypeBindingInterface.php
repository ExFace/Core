<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Interface for widget property bindings
 * 
 * @author Andrej Kabachnik
 *        
 */
interface WidgetPropertyDataTypeBindingInterface extends WidgetPropertyBindingInterface
{    
    /**
     * Returns TRUE if the binding points to a datatype.
     *
     * @return bool
     */
    public function isBoundToDataType() : bool;

    /**
     *
     * @return DataTypeInterface|null
     */
    public function getDataType() : ?DataTypeInterface;
}