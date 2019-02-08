<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

trait EnumDynamicDataTypeTrait {
    
    private $values = array();
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getValues()
     */
    public function getValues()
    {
        return array_keys($this->values);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        return $this->values;
    }
    
    /**
     * Defines the allowed values for the enumeration as value-label pairs.
     * 
     * Example for a typical type enumeration:
     * {
     *  values: {
     *      "TYPE1": "Name of type 1",
     *      "TYPE2": "Name of type 2"
     *  }
     * }
     * 
     * @param UxonObject|array $uxon_or_array
     * @throws DataTypeConfigurationError
     */
    public function setValues($uxon_or_array)
    {
        if ($uxon_or_array instanceof UxonObject) {
            $this->values = $uxon_or_array->toArray();
        } elseif (is_array($uxon_or_array)) {
            $this->values = $uxon_or_array;
        } else {
            throw new DataTypeConfigurationError($this, 'Invalid format for enumeration values ("' . gettype($uxon_or_array) . '") given: expecting UXON or array!', '6XGN4ES');
        }
    }
    
    public function parse($string)
    {
        if ($string === null || $string === '') {
            return $string;
        }
        
        if (false === array_key_exists($string, $this->values)) {
            throw $this->createValidationError('Value "' . $string . '" not part of enumeration data type ' . $this->getAliasWithNamespace() . '!', '6XGN2H6');
        }
        
        return $string;
    }
}