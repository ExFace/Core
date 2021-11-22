<?php
namespace exface\Core\DataTypes;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;

/**
 * PHP password hashes
 * 
 * @author aka
 *
 */
class PasswordHashDataType extends StringDataType
{
    private $hashAlgorithm = null;
    
    /**
     * 
     * @param string $password
     * @return bool
     */
    public static function isHash(string $password) : bool
    {
        $nfo = password_get_info($password);
        return $nfo['algo'] !== null && $nfo['algo'] !== 0;
    }
    
    /**
     * 
     * @param string $password
     * @return string
     */
    public function hash(string $password) : string
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::isSensitiveData()
     */
    public function isSensitiveData() : bool
    {
        return true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::parse()
     */
    public function parse($value)
    {
        if ($this->isHash($value)) {
            return $value;
        }
        return parent::parse($value);
    }
    
    /**
     * Expression to fetch additional password policy options: e.g. a formula to load them from somewhere.
     * 
     * The expression MUST produce a valid config UXON for this data type. For example, the password
     * policy for built-in user passwords of the workbench is loaded from the system config file like
     * this: `=GetConfig('SECURITY.PASSWORD_CONFIG', 'exface.Core')`.
     * 
     * @uxon-property password_policy_config
     * @uxon-type string
     * @uxon-template =GetConfig('SECURITY.PASSWORD_CONFIG', 'exface.Core')
     * 
     * @param string $string
     * @throws DataTypeConfigurationError
     * @return PasswordHashDataType
     */
    public function setPasswordPolicyConfig(string $string) : PasswordHashDataType
    {
        $expr = ExpressionFactory::createFromString($this->getWorkbench(), $string);
        if (! $expr->isFormula() || ! $expr->isStatic()) {
            throw new DataTypeConfigurationError($this, "The 'password_policy_config' proberty only supports static formulas as value!");
        }
        $uxon = $expr->evaluate();
        if (! $uxon->isEmpty()) {
            $this->importUxonObject($uxon);
        }
        return $this;
    }
}