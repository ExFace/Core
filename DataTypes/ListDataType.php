<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Data type for delimited lists.
 * 
 * This data type helps working with lists of values separated by delimiters like commas, semicolons, etc.
 * 
 * It was primarily designed as the result type for `:LIST` and `:LIST_DISTINCT` aggregators.
 * 
 * @author Andrej Kabachnik
 *
 */
class ListDataType extends StringDataType
{
    private $delimiter = null;
    
    private $valuesDatatype = null;

    /**
     * 
     * @param mixed $string
     * @throws DataTypeCastingError
     * @return string
     */
    public function parse($list)
    {
        if ($this->isValueEmpty($list)) {
            return $list;
        }
        $list = parent::parse($list);

        $parsedVals = [];
        $valuesType = $this->getValuesDataType();
        $vals = explode($this->getListDelimiter(), $list);
        foreach ($vals as $val) {
            $parsedVals = $valuesType->parse(trim($val));
        }
        $parsed = implode($this->getListDelimiter(), $parsedVals);
        return $parsed;        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::format()
     */
    public function format($list = null) : string
    {
        return $this::formatAsList($list, $this->getListDelimiter(), $this->getValuesDataType());
    }

    /**
     * 
     * @param array|string[] $stringOrArray
     * @param string $delimiter
     * @param \exface\Core\Interfaces\DataTypes\DataTypeInterface|null $valueDataType
     * @throws \exface\Core\Exceptions\DataTypes\DataTypeCastingError
     * @return string
     */
    public static function formatAsList($stringOrArray, string $delimiter, DataTypeInterface $valueDataType = null) : string
    {
        $vals = [];
        switch (true) {
            case $stringOrArray === null || $stringOrArray === '':
                return $stringOrArray;
            case is_array($stringOrArray):
                $vals = $stringOrArray;
                break;
            case is_string($stringOrArray):
                $vals = explode($delimiter, $stringOrArray);
                break;
            default:
                throw new DataTypeCastingError('Cannot format "' . $stringOrArray . '" as list: expecting delimited string or array');
        }
        $formattedVals = [];
        foreach ($vals as $val) {
            $formattedVals[] = $valueDataType ? $valueDataType->format(trim($val)) : trim($val);
        }
        return implode(static::formatDelimiter($delimiter), $formattedVals);
    }

    /**
     * Returns the delimiter enhanced for user-facing formatting: adds a trailing space to a `,`
     * 
     * @param string $delimiter
     * @return string
     */
    public static function formatDelimiter(string $delimiter) : string
    {
        switch ($delimiter) {
            case ',':
            case '.':
            case ';':
                return $delimiter . ' ';
        }
        return $delimiter;
    }
    
    /**
     * Data type of the values in the list
     *
     * @uxon-property values_data_type
     * @uxon-type \exface\Core\CommonLogic\DataTypes\AbstractDataType
     * @uxon-template {"alias": ""}
     * 
     * @param $data_type_or_string
     * @return EncryptedDataType
     */
    public function setValuesDataType($dataTypeOrUxonOrString) : ListDataType
    {
        switch (true) {
            case $dataTypeOrUxonOrString instanceof UxonObject:
                if (! $dataTypeOrUxonOrString->hasProperty('alias')) {
                    $datatype = DataTypeFactory::createBaseDataType($this->getWorkbench());
                    $datatype->importUxonObject($dataTypeOrUxonOrString);
                } else {
                    $datatype = DataTypeFactory::createFromUxon($this->getWorkbench(), $dataTypeOrUxonOrString);
                }
                break;
            case is_string($dataTypeOrUxonOrString):
                $datatype = DataTypeFactory::createFromString($this->getWorkbench(), $dataTypeOrUxonOrString);
                break;
            case $dataTypeOrUxonOrString instanceof DataTypeInterface:
                $datatype = $dataTypeOrUxonOrString;
                break;
            default: 
                throw new DataTypeConfigurationError($this, 'Cannot set values data type of "' . $this->getAliasWithNamespace() . '": expecting an instantiated data type, a selector string or a UXON model, got ' . gettype($dataTypeOrUxonOrString) . '" instead!');
        }
        
        if ($datatype instanceof ListDataType) {
            throw new DataTypeConfigurationError($this, 'Cannot use a list data type inside "' . $this->getAliasWithNamespace() . '": please specify a different type for values inside the list!');
        }
        
        $this->valuesDatatype = $datatype;
        
        return $this;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\DataTypes\DataTypeInterface
     */
    public function getValuesDataType() : DataTypeInterface
    {
        if ($this->valuesDatatype === null) {
            $this->valuesDatatype = DataTypeFactory::createBaseDataType($this->getWorkbench());
        }
        return $this->valuesDatatype;
    }

    /**
     * Delimiter to separate list values - `,` by default
     * 
     * @uxon-property list_delimiter
     * @uxon-type string
     * @uxon-default ,
     * 
     * @param string $value
     * @return \exface\Core\DataTypes\ListDataType
     */
    public function setListDelimiter(string $value) : ListDataType
    {
        $this->delimiter = $value;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getListDelimiter() : string
    {
        return $this->delimiter ?? EXF_LIST_SEPARATOR;
    }
}