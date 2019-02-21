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
     * 
     * @return array [ $value => $label ]
     */
    public function getLabels();
    
    /**
     * Returns an array [ value => label ].
     * 
     * @return array
     */
    public function toArray() : array;
}
?>