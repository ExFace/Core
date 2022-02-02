<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Exceptions\LogicException;

trait EnumDynamicDataTypeTrait {
    
    private $values = array();
    
    private $showValues = true;
    
    private $valueLabelDelimiter = ' ';
    
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
        $labels = $this->values;

        if ($this->getShowValues() === true) {
            foreach ($labels as $val => $label) {
                if (strcasecmp($val, $label) !== 0) {
                    $labels[$val] = $this->buildLabel($val, $label);
                }
            }
        }
        
        return $labels;
    }
    
    protected function buildLabel($value, $text) : string
    {
        if ($this->getShowValues() === true) {
            return $value . $this->getValueLabelDelimiter() . $text;
        }
        return $text;
    }
    
    public function getLabelOfValue($value = null) : string
    {
        $value = $value ?? $this->getValue();
        if ($value === null) {
            throw new LogicException('Cannot get text label for an enumeration value: neither an internal value exists, nor is one passed as parameter');
        }
        $text = $this->values[$value];
        if ($text === null) {
            throw $this->createValidationError('Value "' . $value . '" not part of enumeration data type ' . $this->getAliasWithNamespace() . '!', '6XGN2H6');
        }
        return $this->buildLabel($value, $text);
    }
    
    /**
     * Defines the allowed values for the enumeration as value-label pairs.
     * 
     * Example for a typical type enumeration:
     * {
     *  "values": {
     *      "TYPE1": "Name of type 1",
     *      "TYPE2": "Name of type 2"
     *  }
     * }
     * 
     * @uxon-property values
     * @uxon-type object
     * @uxon-template {"": ""}
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
    
    protected function getShowValues() : bool
    {
        return $this->showValues;
    }
    
    /**
     * If TRUE, the value will be automatically added in front of the label.
     * 
     * The `value_label_delimiter` will be used as separator.
     * 
     * @uxon-property show_values
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return EnumDataTypeInterface
     */
    public function setShowValues(bool $trueOrFalse) : EnumDataTypeInterface
    {
        $this->showValues = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getValueLabelDelimiter() : string
    {
        return $this->valueLabelDelimiter;
    }
    
    /**
     * If show_values is TRUE, this string will be used to glue the value to the label.
     * 
     * By default, the delimiter is a single space character.
     * 
     * @uxon-property value_label_delimiter
     * @uxon-type string
     * @uxon-default  
     * 
     * @param string $string
     * @return EnumDataTypeInterface
     */
    public function setValueLabelDelimiter(string $string) : EnumDataTypeInterface
    {
        $this->valueLabelDelimiter = $string;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::toArray()
     */
    public function toArray() : array
    {
        return $this->values;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see AbstractDataType::format()
     */
    public function format($value = null) : string
    {
        $value = parent::format($value);
        if ($value === '') {
            return '';
        }
        return $this->getLabelOfValue($value) ?? $value;
    }
}