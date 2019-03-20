<?php
namespace exface\Core\Interfaces\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\LogicException;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

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
     * 
     * @return array [ $value => $label ]
     */
    public function getLabels();
    
    /**
     * Returns the text label for the current internal value or the given value.
     * 
     * @param mixed $value
     * 
     * @throws LogicException if there is neither an internal nor a given value
     * @throws DataTypeValidationError if a value is given, but does not match the enum
     * 
     * @return string
     */
    public function getLabelOfValue($value = null) : string;
    
    /**
     * Returns an array [ value => label ].
     * 
     * @return array
     */
    public function toArray() : array;
}
?>