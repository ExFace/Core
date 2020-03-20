<?php
namespace exface\Core\DataTypes;

use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;

/**
 * Data type for binary data (e.g. media file contents, etc.).
 * 
 * @author Andrej Kabachnik
 *
 */
class BinaryDataType extends StringDataType
{
    const ENCODING_BASE64 = 'base64';
    const ENCODING_HEX = 'hex';
    
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
     * @uxon-type [base64,hex]
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
}