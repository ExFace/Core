<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\CommonLogic\UxonObject;
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
    
    public function __construct(DataTypeSelectorInterface $selector, $value = null, UxonObject $configuration = null)
    {
        $workbench = $selector->getWorkbench();
        $uxon = $workbench->getConfig()->getOption('SECURITY.PASSWORD_CONFIG');
        if (! $uxon->isEmpty()) {
            $this->importUxonObject($uxon);
        }
        parent::__construct($selector, $value, $configuration);
    }
    
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
     * Set the configuration key and app where the password policy for the datatype is defined.
     * Password policy can be defined in the app configuration by setting a minimal length, a maximal length and a regular expression.
     * 
     * @uxon-property password_policy_config
     * @uxon-type string
     * @uxon-default =GetConfig()
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