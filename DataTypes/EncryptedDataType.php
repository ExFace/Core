<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Exceptions\EncryptionError;

/**
 * Allows to encrypt any other data using the widespread libsodium encryption library.
 * 
 * The data type of the raw data must specified in the `inner_datatype`, so that it
 * can be verified properly. The encryption is done automatically whenever data is
 * parsed. This way, data is encrypted throughout most operations in the workbench. 
 * 
 * Only right before being used - e.g. as widget value, widget data, etc. the data will
 * be decrypted.
 * 
 * The encrypted data is encoded as Base64 and receives a special prefix (`$$~~` by default)
 * in order to distinguish raw data and encrypted data easily. The refix can be changed
 * in the data type configuration.
 * 
 * ## Example
 * 
 * Here is the configuration for the encryption of user credentials for data connections.
 * The original data type is the UXON schema for data connection configuration. This type
 * is used to work with decrypted data while the genericy `exface.Core.EncryptedData`
 * is responsible for the encrypted state. 
 * 
 * ```
 * {
 *  "inner_data_type": "exface.Core.UxonDataConnection"
 * }
 * 
 * ```
 * 
 * @author Ralf Mulansky
 *
 */
class EncryptedDataType extends AbstractDataType
{
    public const ENCRYPTION_PREFIX_DEFAULT = '$$~~';
    
    private $innerDatatype = null;
    
    private $encryptionPrefix = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($value) : string
    {
        $exface = $this->getWorkbench();
        if (StringDataType::startsWith($value, $this->getEncryptionPrefix(), true) === true) {
            $decrypt = self::decrypt(self::getSecret($exface), $value, $this->getEncryptionPrefix());
            $string = $this->getInnerDataType()->parse($decrypt);
            $encrypt = self::encrypt(self::getSecret($exface), $string, $this->getEncryptionPrefix());
            return $encrypt;
        }
        $string = $this->getInnerDataType()->parse($value);
        $encrypted = self::encrypt(self::getSecret($exface), $string, $this->getEncryptionPrefix());
        return $encrypted;        
    }
    
    /**
     * Check if the given string is encrypted. String is seen as encrypted if it starts with the encryption prefix.
     * 
     * @param string|NULL $value
     * @return boolean
     */
    public function isValueEncrypted($value)
    {
        if (static::isValueEmpty($value)) {
            return false;
        }
        return StringDataType::startsWith($value, $this->getEncryptionPrefix());
    }
    
    /**
     * Encrypt the given data with the given secret.
     * Secret needs to be a base64 encoded string.
     * 
     * @param string $secret
     * @param string $data
     * @param string $prefix
     * 
     * @throws RuntimeException
     * @throws EncryptionError
     * 
     * @return string
     */
    public static function encrypt(string $secret, string $data, string $prefix = null) : string
    {
        if ($data === null || $data === '') {
            return $data;
        }
        if (! function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('Required PHP extension "sodium" not found!');
        }
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encryptedData = sodium_crypto_secretbox($data, $nonce, sodium_base642bin($secret, 1));
        } catch (\Throwable $e) {
            throw new EncryptionError('Cannot encrypt data: ' . $e->getMessage());
        }
        $encryptedB64 = sodium_bin2base64($nonce . $encryptedData, 1);
        return ($prefix !== null ? $prefix : '') . $encryptedB64;
    }
    
    /**
     * Decrypt the string, using the given secret, and removing the prefix.
     * Secret needs to be a base64 encoded string.
     * 
     * @param string $secret
     * @param string $data
     * @param string $prefix
     * 
     * @throws RuntimeException
     * @throws EncryptionError
     * 
     * @return string
     */
    public static function decrypt(string $secret, string $data, string $prefix = null) : string
    {
        if ($data === null || $data === '') {
            return $data;
        }
        if (! function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException('Required PHP extension "sodium" not found!');
        }
        try {
            $key = sodium_base642bin($secret, 1);
            if ($prefix !== null && $prefix !== '') {
                if (StringDataType::startsWith($data, $prefix)) {
                    $data = substr($data, strlen($prefix));
                }
            }
            $decoded = sodium_base642bin($data, 1);
            $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
            $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
            return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        } catch (\Throwable $e) {
            throw new EncryptionError('Cannot decrypt data: ' . $e->getMessage());
        }
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
    
    /**
     * Returns secret that is saved as option in system config. 
     * 
     * If secret in config is empty a new one is generated and saved. 
     * Secret should be saved base64 encoded!
     * 
     * @param WorkbenchInterface $workbench
     * @throws RuntimeException
     * @return string
     */
    public static function getSecret(WorkbenchInterface $workbench) : string
    {
        $ctxtScope = $workbench->getContext()->getScopeInstallation();
        try {
            $key = $ctxtScope->getVariable('sodium');
        } catch (ConfigOptionNotFoundError $e) {
            $key = null;
        }
        //$key = $this->getConfig()->getOption("ENCRYPTION.SALT");
        if ($key === null || $key === '') {
            if (! function_exists('sodium_crypto_kdf_keygen')) {
                throw new RuntimeException('Required PHP extension "sodium" not found!');
            }
            $key = sodium_crypto_kdf_keygen();
            $key = sodium_bin2base64($key, 1);
            $ctxtScope->setVariable("sodium", $key);
        }
        return $key;
    }
}