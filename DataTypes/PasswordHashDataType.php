<?php
namespace exface\Core\DataTypes;

/**
 * PHP password hashes
 * 
 * @author aka
 *
 */
class PasswordHashDataType extends StringDataType
{
    private $hashAlgorithm = null;
    
    public static function isHash(string $password) : bool
    {
        $nfo = password_get_info($password);
        return $nfo['algo'] !== 0;
    }
    
    protected function hash(string $password) : string
    {
        return password_hash($password, $this->getHashAlgorithm());
    }
    
    /**
     * 
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verify(string $password, string $hash) : bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     *
     * @return int
     */
    protected function getHashAlgorithm() : int
    {
        if ($this->hashAlgorithm !== null) {
            return constant('PASSWORD_' . strtoupper($this->hashAlgorithm));
        } else {
            return PASSWORD_DEFAULT;
        }
    }
    
    /**
     * One of the password hashing algorithms suppoerted by PHP.
     *
     * @link https://www.php.net/manual/en/function.password-hash.php
     *
     * @uxon-property hash_algorithm
     * @uxon-type [default,bcrypt,argon2i,argon2id]
     *
     * @param string $value
     * @return PasswordHashDataType
     */
    public function setHashAlgorithm(string $value) : PasswordHashDataType
    {
        $this->hashAlgorithm = $value;
        return $this;
    }
    
    public function isSensitiveData() : bool
    {
        return true;
    }
}