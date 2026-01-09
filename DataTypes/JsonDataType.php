<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\Debugger;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\DataTypes\JsonSchemaValidationError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use JsonSchema\Validator;

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
    public static function cast($stringOrArrayOrObject)
    {
        if (is_array($stringOrArrayOrObject) || $stringOrArrayOrObject instanceof \stdClass) {
            return $stringOrArrayOrObject;
        }
        
        $stringOrArrayOrObject = trim($stringOrArrayOrObject);
        
        if ($stringOrArrayOrObject === '') {
            return '{}';
        }
        
        if ($stringOrArrayOrObject === null) {
            return null;
        }
        
        return $stringOrArrayOrObject;
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
    public function parse($stringOrArrayOrObject)
    {
        if ($stringOrArrayOrObject === '') {
            return '{}';
        }
        
        if ($stringOrArrayOrObject === null) {
            return null;
        }
        
        try {
            if (is_array($stringOrArrayOrObject) || $stringOrArrayOrObject instanceof \stdClass) {
                $instance = $stringOrArrayOrObject;
            } else {
                $instance = $this::decodeJson($stringOrArrayOrObject, false);
            }
        } catch (DataTypeCastingError $e) {
            throw $this->createValidationParseError($stringOrArrayOrObject, null, null, $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw $this->createValidationParseError($stringOrArrayOrObject, 'Invalid value "' . $stringOrArrayOrObject . '" for data type ' . $this->getAliasWithNamespace() . '!', false,null, $e);
        }
        return $this::encodeJson($instance, $this->getPrettify());
    }
    
    /**
     * Decodes a JSON string into a PHP array (default!) or \stdClass object.
     * 
     * WARNING: handling a complex JSON as an array may have side-effects:
     * empty objects `{}` will not be different from empty arrays `[]`, thus
     * transforming a string into an array and back may not work as expected!
     * 
     * @param string $anything
     * @param bool $toArray
     * @throws DataTypeCastingError
     * @return array|\stdClass|mixed
     */
    public static function decodeJson(string $anything, bool $toArray = true)
    {
        $arrayOrObj = json_decode($anything, ($toArray === true ? true : null));
        if ($arrayOrObj === null && $anything !== null) {
            throw new DataTypeCastingError('Cannot parse string "' . substr($anything, 0, 50) . '" as JSON: ' . json_last_error_msg() . ' in JSON decoder!');
        }
        return $arrayOrObj;
    }

    /**
     * 
     * @param mixed $json
     * @param bool $prettify
     * @return string
     */
    public static function encodeJson($anything, bool $prettify = false): ?string
    {
        if ($anything === null) {
            return null;
        }
        $options = 0;
        if ($prettify === true) {
            $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        }
        $result = json_encode($anything, $options);
        if ($result === false && $anything !== false) {
            $trunc = Debugger::printVariable($anything, false, 0);
            $trunc = StringDataType::truncate($trunc ?? '', 60, false, true, true, true);
            throw new DataTypeCastingError('Cannot encode ' . gettype($anything) . ' "' . $trunc . '" as JSON: ' . json_last_error_msg() . ' in JSON encoder!', null, null, $anything);
        }
        return $result;
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
    
    /**
     * Returns a pretty printed string for the given JSON, array or stdClass object.
     * 
     * @param string|array|object $json
     * @return string
     */
    public static function prettify($json) : string
    {
        if (is_string($json)) {
            $obj = json_decode($json);
        } else {
            $obj = $json;
        }
        return json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Validate json against a specified json schema.
     *
     * @param string|array|object  $json
     * @param string|array|\StdClass $schemaJson
     * @return bool
     */
    public static function validateJsonSchema(mixed $json, mixed $schemaJson) : bool
    {
        $validator = (new Validator());
        $json = is_string($json) ? json_decode($json) : $json;
        $schemaJson = is_string($schemaJson) ? json_decode($schemaJson) : $schemaJson;
        $validator->validate($json, $schemaJson);

        $errors = $validator->getErrors();
        if (count($errors) > 0) {
            throw new JsonSchemaValidationError(
                $errors,
                'JSON does not match schema: found ' . count($errors) . ' errors',
                null,
                null,
                $json);
        }
        
        return $validator->isValid();
    }

    /**
     * Convert a given metamodel data type to a JSON schema type
     * 
     * @link https://cswr.github.io/JsonSchema/spec/basic_types/
     *
     * @param DataTypeInterface $dataType
     * @return array|string[]
     */
    public static function convertDataTypeToJsonSchemaType(DataTypeInterface $dataType) : array
    {
        switch (true) {
            case $dataType instanceof IntegerDataType:
                return ['type' => 'integer'];
            case ($dataType instanceof NumberDataType) && $dataType->getBase() === 10:
                return ['type' => 'number'];
            case $dataType instanceof BooleanDataType:
                return ['type' => 'boolean'];
            case $dataType instanceof ArrayDataType:
                return ['type' => 'array'];
            case $dataType instanceof EnumDataTypeInterface:
                return ['type' => 'string', 'enum' => $dataType->getValues()];
            case $dataType instanceof TimeDataType:
                return ['type' => 'string', 'format' => 'time'];
            case $dataType instanceof DateTimeDataType:
                return ['type' => 'string', 'format' => 'datetime'];
            case $dataType instanceof DateDataType:
                return ['type' => 'string', 'format' => 'date'];
            case $dataType instanceof BinaryDataType:
                if ($dataType->getEncoding() == 'base64') {
                    return ['type' => 'string', 'format' => 'byte'];
                } else {
                    return ['type' => 'string', 'format' => 'binary'];
                }
            case $dataType instanceof StringDataType:
                return ['type' => 'string'];
            case $dataType instanceof HexadecimalNumberDataType:
                return ['type' => 'string'];
            default:
                throw new InvalidArgumentException('Datatype: ' . $dataType->getAlias() . ' not recognized.');
        }
    }

    /**
     * Sanitize a string for JsonPath by replacing all reserved characters `[$@.\[\]*,?]` with the character you
     * provided.
     * 
     * @param string $string
     * @param string $replacement
     * @return string
     */
    public static function sanitizeForJsonPath(string $string, string $replacement = '_') : string
    {
        return preg_replace('/[\\\\\/$@.\[\]*,?]/', $replacement, $string);
    }
}