<?php
namespace exface\Core\DataTypes;

use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\DataTypes\DataTypeConfigurationError;

/**
 * Data type for passwords and secrets.
 * 
 * This data type supports password policy validation and hashing. In particular,
 * password strength can be checked using regular expressions: e.g.
 * 
 * ```
 *  {
 *     "validator_regex": "/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?([^\\w\\s]|[_])).{8,}$/",
 *     "validation_error_code": "7INNL87" 
 *  }
 * 
 * ```
 * 
 * Keep in mind, that auto-generated hints and errors for string validation based
 * on regular expressions are not really helpful, as regexes are not really readable.
 * Be sure to include a `validation_error_code` pointing to a translatable message
 * in the meta model or at least a `validation_error_text` for a static message.
 * 
 * Although this data type offers hashing methods and supports validation of
 * hashed data along with plain-text data, using this data type is not enough
 * to actively hash data in a data source: use the `PasswordHashingBehavior`
 * or data source logic additionally to really perform hashing.
 * 
 * @author Andrej Kabachnik
 *
 */
class PasswordDataType extends StringDataType
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
     * Matches that a password matches a hash.
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
     * Returns the value of the PHP constant corresponding to the selected hash algorithm.
     * 
     * NOTE: this value is NOT the one passed to `setHashAlgorithm()`, but rather the
     * one used by PHPs built-in `password_hash()` function.
     * 
     * See https://www.php.net/manual/en/password.constants.php for a list of available
     * constants and the corresponding values.
     * 
     * @link https://www.php.net/manual/en/password.constants.php
     * @return string|int|null
     */
    protected function getHashAlgorithm()
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
     * @return PasswordDataType
     */
    public function setHashAlgorithm(string $value) : PasswordDataType
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
        if ($this::isHash($value)) {
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
     * @return PasswordDataType
     */
    public function setPasswordPolicyConfig(string $string) : PasswordDataType
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