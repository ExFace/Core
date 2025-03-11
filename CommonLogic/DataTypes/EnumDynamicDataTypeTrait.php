<?php
namespace exface\Core\CommonLogic\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

trait EnumDynamicDataTypeTrait {
    
    private $values = [];

    private $valueHints = [];
    
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
    
    public function getLabelOfValue($value = null) : ?string
    {
        $value = $value ?? $this->getValue();
        $labels = $this->getLabels();
        $label = $labels[$value] ?? null;
        if ($label === null) {
            foreach ($labels as $key => $labelValue) {
                if (strcasecmp($value, $key) === 0) {
                    $label = $labelValue;
                    continue;
                }
            }
        }
        if ($label === null) {
            return null;
        }
        return $this->buildLabel($value, $label);
    }
    
    /**
     * Defines the allowed values for the enumeration as value-label pairs.
     * 
     * Example for a typical type enumeration:
     * 
     * ```
     *  {
     *      "values": {
     *          "TYPE1": "Name of type 1",
     *          "TYPE2": "Name of type 2"
     *      }
     *  }
     * 
     * ```
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

    /**
     * Hints/tooltips to describe each value in addition to its label
     * 
     * @uxon-property value_hints
     * @uxon-type object
     * @uxon-template {"": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxonArray
     * @return \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface
     */
    protected function setValueHints(UxonObject $uxonArray) : EnumDataTypeInterface
    {
        $this->valueHints = $uxonArray->toArray();
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getValueHints()
     */
    public function getValueHints() : array
    {
        return $this->valueHints;
    }

    /**
     * 
     * @param string|int|null $value
     * @return string|null
     */
    public function getHintOfValue($value) : ?string
    {
        return $this->valueHints[$value] ?? null;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see AbstractDataType::parse()
     */
    public function parse($string)
    {
        // Do not cast the value to aviod type mismatches with array keys (e.g. do not normalize numbers!)
        $value = $string === null ? null : trim($string);
        
        $valueInArray = array_key_exists($value, $this->values);
        
        // Convert all sorts of empty values to NULL except if they are explicitly
        // part of the enumeration: e.g. an empty string should become null if the
        // enumeration does not include the empty string explicitly.
        // TODO #null-or-NULL does the NULL constant need to pass parsing?
        if (($this->isValueEmpty($value) || static::isValueLogicalNull($value)) && $valueInArray === false) {
            return null;
        }
        
        if (false === $valueInArray) {
            throw $this->createValidationError('Value "' . $string . '" not part of enumeration data type ' . $this->getAliasWithNamespace() . '!', '6XGN2H6');
        }
        
        return $value;
    }
    
    /**
     * 
     * @return bool
     */
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