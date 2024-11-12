<?php
namespace exface\Core\Interfaces\DataTypes;

use exface\Core\CommonLogic\UxonObject;

interface EnumDataTypeInterface extends DataTypeInterface
{

    /**
     * 
     * @return array
     */
    public function getValues();
    
    /**
     * 
     * @param UxonObject|array $uxon_or_array
     * @return EnumDataTypeInterface
     */
    public function setValues($uxon_or_array);

    /**
     * 
     * @return string[]
     */
    public function getValueHints() : array;
    
    /**
     * 
     * 
     * @return array [ $value => $label ]
     */
    public function getLabels();
    
    /**
     * Returns the text label for the current internal value or the given value.
     * 
     * Returns NULL if the value does not fit the enumeration. If you need to check if
     * the value is valid, use the explicit `isValidValue()`, `cast()` or `parse()` instead!
     * 
     * @param mixed $value
     * 
     * @return string|NULL
     */
    public function getLabelOfValue($value = null) : ?string;

    /**
     * 
     * @param mixed $value
     * @return string|null
     */
    public function getHintOfValue($value) : ?string;
    
    /**
     * Returns an array [ value => label ].
     * 
     * @return array
     */
    public function toArray() : array;
}
?>