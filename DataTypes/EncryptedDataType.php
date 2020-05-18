<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Interfaces\WorkbenchInterface;

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
    public static function encrypt(WorkbenchInterface $exface, string $data)
    {
        $key = $exface->getSecret();        
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encryptedData = sodium_crypto_secretbox($data, $nonce, sodium_base642bin($key, 1));
        return sodium_bin2base64($nonce . $encryptedData, 1);
    }
    
    // decrypt encrypted string
    public static function decrypt(WorkbenchInterface $exface, string $data)
    {
        $key = $exface->getSecret();
        $key = sodium_base642bin($key, 1);
        $decoded = sodium_base642bin($data, 1);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
    }
}
?>