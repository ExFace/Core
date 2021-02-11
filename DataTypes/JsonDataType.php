<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\InvalidArgumentException;

class JsonDataType extends TextDataType
{

    private $prettify = false;
    
    private $schema = null;

    /**
     * Returns true if the JSON should be formatted in human-readable form, false otherwise.
     * 
     * @return boolean
     */
    public function getPrettify()
    {
        return $this->prettify;
    }

    /**
     * Set to true to export JSON in a human readable form (line-breaks, intendations).
     * 
     * default: false
     * 
     * e.g:
     * false:
     * {"key1":"value1","key2":"value2"}
     * 
     * true:
     * {
     *     "key1": "value1",
     *     "key2": "value2"
     * }
     * 
     * @uxon-property prettify
     * @uxon-type boolean
     * 
     * @param boolean $prettify
     * @return JsonDataType
     */
    public function setPrettify($prettify)
    {
        $this->prettify = BooleanDataType::cast($prettify);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::cast()
     */
    public static function cast($string)
    {
        $string = trim($string);
        
        if ($string === '') {
            return '{}';
        }
        
        if ($string === null) {
            return null;
        }
        
        return $string;
    }
    
    public static function isValueEmpty($string) : bool
    {
        return parent::isValueEmpty($string) === true || $string === '{}' || $string === '[]';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::parse()
     */
    public function parse($string)
    {
        if ($string === '') {
            return '{}';
        }
        
        if ($string === null) {
            return null;
        }
        
        try {
            $array = $this::decodeJson($string);
        } catch (DataTypeCastingError $e) {
            throw $this->createValidationError($e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw $this->createValidationError('Invalid value "' . $string . '" for data type ' . $this->getAliasWithNamespace() . '!', null, $e);
        }
        return $this::encodeJson($array, $this->getPrettify());
    }

    /**
     * 
     * @param string $string
     * @throws DataTypeCastingError
     * @return array
     */
    public static function decodeJson(string $string): array
    {
        $array = json_decode($string, true);
        if (is_array($array)) {
            return $array;
        }
        throw new DataTypeCastingError('Cannot parse string "' . substr($string, 0, 50) . '" as UXON: ' . json_last_error_msg() . ' in JSON decoder!');
    }

    /**
     * 
     * @param array $json
     * @param boolean $prettify
     * @return string
     */
    public static function encodeJson(array $json, $prettify = false): string
    {
        if ($prettify === true) {
            $params = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        }
        return json_encode($json, $params);
    }
    
    /**
     *
     * @return string
     */
    public function getSchema() : ?string
    {
        return $this->schema;
    }
    
    /**
     * 
     * @uxon-property schema
     * @uxon-type string
     * 
     * @param string $value
     * @return JsonDataType
     */
    public function setSchema(string $value) : JsonDataType
    {
        $this->schema = $value;
        return $this;
    }
    
    /**
     * 
     * @param array $json
     * @param string $path
     * @throws RuntimeException
     * @return mixed
     */
    public static function filterXPath($json, string $path)
    {
        switch (true) {
            case is_array($json):
                $array = $json;
                break;
            case $json instanceof \stdClass:
                $array = (array) $json;
                break;
            case $json === null:
            case $json === '':
                return $json;
            case is_string($json):
                $array = json_decode($json, true);
                break;
            default:
                throw new InvalidArgumentException('Cannot apply XPath filter to JSON: not a valid JSON!');
        }
        
        return ArrayDataType::filterXPath($array, $path);
    }
}