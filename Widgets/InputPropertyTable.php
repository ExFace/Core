<?php
namespace exface\Core\Widgets;

use exface\Core\Widgets\Traits\SingleValueInputTrait;

/**
 * A tabular input for a flat JSON object: keys in one column, values in the other.
 * 
 * @author Andrej Kabachnik
 *
 */
class InputPropertyTable extends Input
{
    use SingleValueInputTrait;
    
    private $allow_add_properties = true;

    private $allow_remove_properties = true;

    /**
     * 
     * @return boolean
     */
    public function getAllowAddProperties() : bool
    {
        return $this->allow_add_properties;
    }

    /**
     * Set to FALSE to prevent the user from adding new properties
     * 
     * @uxon-property allow_add_properties
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return InputPropertyTable
     */
    public function setAllowAddProperties(bool $value) : InputPropertyTable
    {
        $this->allow_add_properties = $value;
    }

    /**
     * 
     * @return bool
     */
    public function getAllowRemoveProperties() : bool
    {
        return $this->allow_remove_properties;
    }

    /**
     * Set to FALSE to prevent the user from removing new properties
     * 
     * @uxon-property allow_remove_properties
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return InputPropertyTable
     */
    public function setAllowRemoveProperties(bool $value) : InputPropertyTable
    {
        $this->allow_remove_properties = $value;
    }
}
?>