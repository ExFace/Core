<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Interfaces\WorkbenchInterface;
<<<<<<< HEAD
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
=======
use exface\Core\Exceptions\RuntimeException;
>>>>>>> refs/remotes/origin/1.x-dev

/**
 * Work in Progress!
 * 
 * EcryptedDataType is a data type wrapper for data that should be encrypted.
 * 
 * @author Ralf Mulansky
 *
 */
class EncryptedDataType extends AbstractDataType
{
    private const ENCRYPTION_PREFIX_DEFAULT = '$$~~';
    
    private $innerDatatype = null;
    
    private $encryptionPrefix = null;
    
    public function parse($value) : string
    {
<<<<<<< HEAD
        if (StringDataType::startsWith($value, $this->getEncryptionPrefix(), true) === true) {
            $decrypt = self::decrypt($$this->getWorkbench(), StringDataType::substringAfter($value, $this->getEncryptionPrefix(), false, true));
            $string = $this->getInnerDataType()->parse($decrypt);
            $encrypt = self::encrypt($this->getWorkbench(), $string);
            return $this->getEncryptionPrefix() . $encrypt;
        }
        $string = $this->getInnerDataType()->parse($value);
        $encrypted = self::encrypt($this->getWorkbench(), $string);
        return $this->getEncryptionPrefix() . $encrypted;        
    }
    
    public function isValueEncrypted(string $value)
    {
        return StringDataType::startsWith($value, $this->getEncryptionPrefix());
    }
    
    public static function encrypt(WorkbenchInterface $exface, string $data, string $prefix = null)
    {
        if ($data === null || $data === '') {
            return $data;
        }
        $key = $exface->getSecret();        
=======
        if (! function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('Required PHP extension "sodium" not found!');
        }
        $key = $exface->getSecurity()->getSecret();        
>>>>>>> refs/remotes/origin/1.x-dev
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encryptedData = sodium_crypto_secretbox($data, $nonce, sodium_base642bin($key, 1));
        if ($prefix === null) {
            return sodium_bin2base64($nonce . $encryptedData, 1);
        }
        return $prefix . sodium_bin2base64($nonce . $encryptedData, 1);
    }
    
    // decrypt encrypted string
    public static function decrypt(WorkbenchInterface $exface, string $data, $prefix = null)
    {
<<<<<<< HEAD
        if ($data === null || $data === '') {
            return $data;
        }
        $key = $exface->getSecret();
=======
        if (! function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException('Required PHP extension "sodium" not found!');
        }
        
        $key = $exface->getSecurity()->getSecret();
>>>>>>> refs/remotes/origin/1.x-dev
        $key = sodium_base642bin($key, 1);
        if ($prefix !== null && $prefix !== '') {
            $data = StringDataType::substringAfter($data, $prefix);
        }
        $decoded = sodium_base642bin($data, 1);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
    }
    
    /**
     * Set the inner datatype
     *
     * @uxon-property inner_data_type
     * @uxon-type metamodel:datatype
     * 
     * @param $data_type_or_string
     * @return EncryptedDataType
     */
    public function setInnerDataType($data_type_or_string) : EncryptedDataType
    {
        if ($data_type_or_string instanceof EncryptedDataType) {
            throw new DataTypeConfigurationError($this, 'Cannot set inner datatype "' . $data_type_or_string . '"! Inner datatype cannot be of type :"'. $this->getAliasWithNamespace(). '" !');
        }
        if ($data_type_or_string instanceof DataTypeInterface) {
            $this->innerDatatype = $data_type_or_string;
        } elseif (is_string($data_type_or_string)) {
            $datatype = DataTypeFactory::createFromString($this->getWorkbench(), $data_type_or_string);
            if ($datatype instanceof EncryptedDataType) {
                throw new DataTypeConfigurationError($this, 'Cannot set inner datatype "' . $data_type_or_string . '"! Inner datatype cannot be of type :"'. $this->getAliasWithNamespace(). '" !');
            }
            $this->innerDatatype = $datatype;
        } else {
            throw new DataTypeConfigurationError($this, 'Cannot set inner datatype "' . $data_type_or_string . '"! ' . gettype($data_type_or_string) . '" given - expecting an instantiated data type or a string selector!');
        }
        return $this;
    }
    
    protected function getInnerDataType() : DataTypeInterface
    {
        if ($this->innerDatatype === null) {
            throw new DataTypeConfigurationError($this, 'No inner datatype set for: "' . $this->getAliasWithNamespace() . '" !');
        }
        return $this->innerDatatype;
    }
    
    /**
     * Set the prefix that should be added to the encrypted string.
     * 
     * @uxon-property encryption_prefix
     * @uxon-type string
     * 
     * @param string $prefix
     * @return EncryptedDataType
     */
    public function setEncryptionPrefix(string $prefix) : EncryptedDataType
    {
        $this->encryptionPrefix = $prefix;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getEncryptionPrefix() : string
    {
        if ($this->encryptionPrefix === null) {
            return self::ENCRYPTION_PREFIX_DEFAULT;
        }
        return $this->encryptionPrefix;
    }
}
?>