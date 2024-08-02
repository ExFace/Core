<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\DataTypes\AbstractDataType;

/**
 * Data type for binary data (e.g. media file contents, etc.).
 * 
 * The data type supports different encodings for binary data as string:
 * 
 * - `base64` - e.g. "dGVzdA==" for the word "test"
 * - `hex` - e.g. "0x74657374" or "74657374" for the word "test"
 * - `binary`- e.g. "01110100011001010111001101110100" for the word "test"
 * 
 * Be sure to set the corrent encoding whereever you use the data type. If
 * no encoding is set explictly, `base64` will be assumed as the most common
 * way to send binary data over the web.
 * 
 * **NOTE**: hex data will be prefixed `0x` internally to distinguish it from
 * the other encodings and standardise values within the workbench. Widgets 
 * and query builders may of course remove this prefix for their needs.
 * 
 * @author Andrej Kabachnik
 *
 */
class BinaryDataType extends AbstractDataType
{
    const ENCODING_BASE64 = 'base64';
    const ENCODING_HEX = 'hex';
    const ENCODING_BINARY = 'binary';
    
    private $lengthMax = null;
    
    private $encoding = self::ENCODING_BASE64;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\StringDataType::parse()
     */
    public function parse($string)
    {
        $string = parent::parse($string);
        
        if ($string === null || $string === '') {
            return $string;
        }
        
        switch ($this->getEncoding()) {
            case self::ENCODING_BASE64:
                if (base64_decode($string, true) === false) {
                    throw new DataTypeValidationError($this, 'Invalid base64 string!');
                }
                break;
            case self::ENCODING_HEX:
                return HexadecimalNumberDataType::cast($string);
            case self::ENCODING_BINARY:
                if (is_numeric($string) === false) {
                    throw new DataTypeValidationError($this, 'Invalid binary string!');
                }
                break;
        }
        
        // validate length
        $length = mb_strlen($string);
        if ($this->getMaxSizeInBytes() > 0 && $length < $this->getMaxSizeInBytes()){
            $excValue = '';
            if (! $this->isSensitiveData()) {
                $excValue = '"' . StringDataType::truncate($string, 60, false, false, true) . '" (' . $length . ')';
            }
            throw $this->createValidationError('The size of the binary ' . $excValue . ' is larger, than the maximum for data type ' . $this->getAliasWithNamespace() . ' (' . $this->getLengthMin() . ')!');
        }
        return $string;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::getValidationDescription()
     */
    protected function getValidationDescription() : string
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $text = '';
        if ($this->getMaxSizeInBytes() > 0) {
            $lengthCond = ' ≤ ' . $this->getMaxSizeInMB();
        }
        if ($lengthCond) {
            $text .= $translator->translate('DATATYPE.VALIDATION.LENGTH_CONDITION', ['%condition%' => $lengthCond]);
        }
        
        if ($text !== '') {
            $text = $translator->translate('DATATYPE.VALIDATION.MUST') . ' ' . $text . '.';
        }
        
        return $text;
    }
    
    /**
     *
     * @return string
     */
    public function getEncoding() : string
    {
        return $this->encoding;
    }
    
    /**
     * The encoding used for binary data: base64 or hex.
     * 
     * @uxon-property encoding
     * @uxon-type [base64,hex,binary]
     * @uxon-default base64
     * 
     * @param string $value
     * @return BinaryDataType
     */
    public function setEncoding(string $value) : BinaryDataType
    {
        $constant = 'static::ENCODING_' . strtoupper($value);
        if (! defined($constant)) {
            throw new DataTypeConfigurationError($this, 'Invalid encoding "' . $value . '" for data type "' . $this->getAliasWithNamespace() . '"!');
        }
        $this->encoding = constant($constant);
        return $this;
    }
    
    /**
     * Maximum size of data in bytes (similar to `length_max` for strings)
     * 
     * @uxon-property max_size
     * @uxon-type integer
     * 
     * @param int $bytes
     * @return BinaryDataType
     */
    public function setLengthMax(int $bytes) : BinaryDataType
    {
        $this->lengthMax = $bytes;
        return $this;
    }
    
    /**
     * 
     * @return int
     */
    public function getMaxSizeInBytes() : ?int
    {
        return $this->lengthMax;
    }
    
    /**
     * 
     * @return float
     */
    public function getMaxSizeInMB() : ?float
    {
        if ($this->getMaxSizeInBytes() === null) {
            return null;
        }
        return $this->getMaxSizeInBytes() / 1024 / 1024;
    }
    
    /**
     * Converts a given string from Base64 to a hexadecimal number like `0xf1a23` - the `x` being the optional prefix
     * 
     * @param string $base64String
     * @param bool $addPrefix0x
     * 
     * @return string
     */
    public static function convertBase64ToHex(string $base64String, bool $addPrefix0x = true) : string
    {
        return static::convertBinaryToHex(static::convertBase64ToBinary($base64String), $addPrefix0x);
    }
    
    /**
     * Converts a given string from Base64 to a binary string.
     * 
     * @param string $base64String
     * @param bool $strict
     * 
     * @throws DataTypeCastingError
     * 
     * @return string
     */
    public static function convertBase64ToBinary(string $base64String, bool $strict = true) : string
    {
        $binary = base64_decode($base64String, $strict);
        if ($binary === false) {
            throw new DataTypeCastingError('Cannot convert Base64 to binary: invalid Base64 string!');
        }
        return $binary;
    }
    
    /**
     * Decodes Base64 encoded text
     * 
     * @param string $base64String
     * @param bool $unescapeUnicode
     * @param bool $strict
     * @return string
     */
    public static function convertBase64ToText(string $base64String, bool $unescapeUnicode = true, bool $strict = false) : string
    {
        $result = static::convertBase64ToBinary($base64String, $strict);
        if ($unescapeUnicode === true) {
            $result = urldecode($result);
        }
        return $result;
    }
    
    /**
     * Converts a given binary string to Base64.
     * 
     * @param string $binaryString
     * @return string
     */
    public static function convertBinaryToBase64(string $binaryString) : string
    {
        return base64_encode($binaryString);
    }
    
    /**
     * Encodes a given string as Base64.
     * 
     * @param string $string
     * @param bool $escapeUnicode
     * @return string
     */
    public static function convertTextToBase64(string $string, bool $escapeUnicode = true) : string
    {
        if ($escapeUnicode === true) {
            $string = urlencode($string);
        }
        return static::convertBinaryToBase64($string);
    }
    
    /**
     * Converts a given binary string to a hexadecimal number like `xf1a23` - the `x` being the optional prefix
     * 
     * @param string $binaryString
     * @param bool $addPrefix0x
     * 
     * @return string
     */
    public static function convertBinaryToHex(string $binaryString, bool $addPrefix0x = true) : string
    {
        return ($addPrefix0x ? HexadecimalNumberDataType::HEX_PREFIX : '') . bin2hex($binaryString);
    }
    
    /**
     * Converts a given hexadecimal string to a Base64 encoded string
     * 
     * @param string $hexString
     * @return string
     */
    public static function convertHexToBase64(string $hexString) : string
    {
        return static::convertBinaryToBase64(static::convertHexToBinary($hexString));
    }
    
    /**
     * Converts a given hexadecimal string to a binary string.
     * 
     * The hexadecimal number may be prefixed with an `x` - it will be automatically removed.
     * 
     * @param string $hexString
     * @throws DataTypeCastingError
     * 
     * @return string
     */
    public static function convertHexToBinary(string $hexString) : string
    {
        $hexString = stripos($hexString, HexadecimalNumberDataType::HEX_PREFIX) === 0 ? substr($hexString, 2) : $hexString;
        $binary = hex2bin($hexString);
        if ($binary === false) {
            throw new DataTypeCastingError('Cannot convert hexadecimal to binary: invalid hexadecimal number!');
        }
        return $binary;
    }
    
    /**
     * Converts a given Base64 string to a data URI with the provided mime type
     * 
     * @param string $base64
     * @param string $mimeType
     * @return string
     */
    public static function convertBase64ToDataUri(string $base64, string $mimeType) : string
    {
        return 'data:' . $mimeType . ';base64,' . $base64;
    }
    
    /**
     * Extracts the Base64-encoded data from a data-URI
     * 
     * @param string $dataURL
     * @return string
     */
    public static function convertDataUriToBase64(string $dataURL) : string
    {
        list(, $data) = explode(';', $dataURL, 2);
        list($enc, $data)      = explode(',', $data, 2);
        switch (strtolower($enc)) {
            case 'base64': return $data;
            default:
                throw new DataTypeCastingError('Invalid encoding "' . $enc . "' found in data URI!");
        }
    }
    
    /**
     * Extracts the mime-type from a data-URI
     * 
     * @param string $dataURL
     * @return string
     */
    public static function convertDataUriToMimeType(string $dataURL) : string
    {
        $tmp = explode(';', $dataURL, 1);
        list(, $mime) = explode(':', $tmp);
        return $mime;
    }
    
    /**
     * Converts a string to Base64URL - a variation of Base64, that is safe to be used URLs
     * 
     * @link https://base64.guru/developers/php/examples/base64url
     * 
     * @param string $data
     * @param bool $escapeUnicode
     * @return string
     */
    public static function convertTextToBase64URL(string $data, bool $escapeUnicode = true) : string
    {
        // First of all you should encode $data to Base64 string
        $b64 = static::convertTextToBase64($data, $escapeUnicode);
        // Convert Base64 to Base64URL by replacing “+” with “-” and “/” with “_”
        $url = strtr($b64, '+/', '-_');
        // Remove padding character from the end of line and return the Base64URL result
        return rtrim($url, '=');
    }
    
    /**
     * Decode data from Base64URL
     * 
     * @link https://base64.guru/developers/php/examples/base64url
     * 
     * @param string $data
     * @param bool $unescapeUnicode
     * @param boolean $strict
     * @return string
     */
    public static function convertBase64URLToText(string $data, bool $unescapeUnicode = true, $strict = false) : string
    {
        // Convert Base64URL to Base64 by replacing “-” with “+” and “_” with “/”
        $b64 = strtr($data, '-_', '+/');
        // Add the correct number of `=` on the right
        $b64 = $b64 . str_repeat('=', 3 - ( 3 + strlen( $data )) % 4 );
        // Decode Base64 string and return the original data
        $result = static::convertBase64ToText($b64, $unescapeUnicode, $strict);
        
        return $result;
    }
    
    /**
     * Converts the given data from the current encoding to Base64.
     * 
     * @param string|null $value
     * @throws RuntimeException
     * @return string
     */
    public function convertToBase64(string $value = null) : ?string
    {
        $value = $value ?? $this->getValue();
        if ($value === null) {
            return $value;
        }
        switch ($this->getEncoding()) {
            case self::ENCODING_BASE64: return $value;
            case self::ENCODING_HEX: return self::convertHexToBase64($value);
            case self::ENCODING_BINARY: return self::convertBinaryToBase64($value);
            default:
                throw new RuntimeException('Cannot convert binary data in ' . $this->getEncoding() . ' to Base64!');
        }
    }
    
    /**
     * Converts the given data from the current encoding to hexadecimal.
     * 
     * @param string $value
     * @param bool $addPrefix0x
     * @throws RuntimeException
     * @return string|NULL
     */
    public function convertToHex(string $value = null, bool $addPrefix0x = true) : ?string
    {
        $value = $value ?? $this->getValue();
        if ($value === null) {
            return $value;
        }
        $value = trim($value);
        switch ($this->getEncoding()) {
            case self::ENCODING_BASE64: return self::convertBase64ToHex($value, $addPrefix0x);
            case self::ENCODING_HEX: ($addPrefix0x ? HexadecimalNumberDataType::HEX_PREFIX : '') . (stripos($value, HexadecimalNumberDataType::HEX_PREFIX) === 0 ? substr($value, 2) : $value);
            case self::ENCODING_BINARY: self::convertBinaryToHex($value, $addPrefix0x);
            default:
                throw new RuntimeException('Cannot convert binary data in ' . $this->getEncoding() . ' to a hexadecimal number!');
        }
    }
    
    /**
     * Converts the given data from the current encoding to a binary string.
     * 
     * @param string $value
     * @throws RuntimeException
     * @return string|NULL
     */
    public function convertToBinary(string $value = null) : ?string
    {
        $value = $value ?? $this->getValue();
        if ($value === null) {
            return $value;
        }
        switch ($this->getEncoding()) {
            case self::ENCODING_BINARY: return $value;
            case self::ENCODING_BASE64: return self::convertBase64ToBinary($value);
            case self::ENCODING_HEX: return self::convertHexToBinary($value);
            default:
                throw new RuntimeException('Cannot convert binary data in ' . $this->getEncoding() . ' to a binary string!');
        }
    }
    
    /**
     * Converts the given data from the current encoding to a data URI.
     * 
     * @param string $mimeType
     * @param string $value
     * @throws RuntimeException
     * @return string|NULL
     */
    public function convertToDataUri(string $mimeType, string $value = null) : ?string
    {
        $value = $value ?? $this->getValue();
        if ($value === null) {
            return $value;
        }
        switch ($this->getEncoding()) {
            case self::ENCODING_BINARY: $b64 = self::convertBinaryToBase64($value);
            case self::ENCODING_BASE64: $b64 = $value;
            case self::ENCODING_HEX: $b64 = self::convertHexToBase64($value);
            default:
                throw new RuntimeException('Cannot convert binary data in ' . $this->getEncoding() . ' to a data URI!');
        }
        return self::convertBase64ToDataUri($b64, $mimeType);
    }
}