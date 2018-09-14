<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

class JsonDataType extends TextDataType
{

    private $prettify = false;

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
        if ($string === '') {
            return '{}';
        }
        
        if (is_null($string)) {
            return null;
        }
        
        return $string;
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
        
        $array = $this::decodeJson($string);
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
}
?>