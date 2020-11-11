<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\Exceptions\RuntimeException;

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
class BinaryDataType extends StringDataType
{
    const ENCODING_BASE64 = 'base64';
    const ENCODING_HEX = 'hex';
    const ENCODING_BINARY = 'binary';
    
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
        return $string;
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
     * Maximum size of data in bytes (same as `length_max`)
     * 
     * @uxon-property max_size
     * @uxon-type integer
     * 
     * @param int $bytes
     * @return BinaryDataType
     */
    public function setMaxSize(int $bytes) : BinaryDataType
    {
        return $this->setLengthMax($bytes);
    }
    
    /**
     * 
     * @return int
     */
    public function getMaxSizeInBytes() : ?int
    {
        return $this->getLengthMax();
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
        return $this->getMaxSizeInBytes() / 1000000;
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
     * @throws DataTypeCastingError
     * 
     * @return string
     */
    public static function convertBase64ToBinary(string $base64String) : string
    {
        $binary = base64_decode($base64String, true);
        if ($binary === false) {
            throw new DataTypeCastingError('Cannot convert Base64 to binary: invalid Base64 string!');
        }
        return $binary;
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
        $binary = hex2bin($hexString, true);
        if ($binary === false) {
            throw new DataTypeCastingError('Cannot convert hexadecimal to binary: invalid hexadecimal number!');
        }
        return $binary;
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
}