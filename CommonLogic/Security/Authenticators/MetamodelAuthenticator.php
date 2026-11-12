<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Security\PasswordAuthenticationTokenInterface;
use exface\Core\CommonLogic\Security\AuthenticationToken\MetamodelUsernamePasswordAuthToken;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Exceptions\UserNotFoundError;
use exface\Core\Factories\UserFactory;
use exface\Core\Widgets\Form;

/**
 * Performs authentication via user data stored in the metamodel.
 * 
 * Passwords are verified against the hash stored in the user model (bcrypt or argon2,
 * as produced by `PasswordDataType` / PHP's `password_hash()`).
 * 
 * @author Andrej Kabachnik
 *
 */
class MetamodelAuthenticator extends AbstractAuthenticator
{
    private const MAX_PASSWORD_LENGTH = 4096;
    
    private const BCRYPT_MAX_PASSWORD_LENGTH = 72;
    
    private const ERROR_CODE = '7AL3J9X';
    
    // Bcrypt hash of a random string - used to keep response times similar for unknown users
    private const DUMMY_HASH = '$2y$13$.XUTXoxVvB6YOIvzag/pWut.r6ShTMoAn7YMSTWxXYC5kIAEwJBkK';
    
    private $authenticatedToken = null;
    
    /**
     * Verifies the password from the token against the hash stored in the metamodel.
     * 
     * SECURITY: all credential failures (unknown user, missing hash, wrong password) produce the
     * same generic message to prevent username enumeration.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        if (! $token instanceof PasswordAuthenticationTokenInterface) {
            throw new AuthenticationFailedError($this, 'Bad credentials.', self::ERROR_CODE);
        }
        $this->checkAuthenticatorDisabledForUsername($token->getUsername());
        
        $password = $token->getPassword() ?? '';
        if ($password === '') {
            throw new AuthenticationFailedError($this, 'Bad credentials.', self::ERROR_CODE);
        }
        
        try {
            $hash = UserFactory::createFromModel($this->getWorkbench(), $token->getUsername())->getPassword();
        } catch (UserNotFoundError $e) {
            $hash = null;
        }
        
        if ($hash === null || $hash === '') {
            $this->isPasswordValid(self::DUMMY_HASH, $password);
            throw new AuthenticationFailedError($this, 'Bad credentials.', self::ERROR_CODE);
        }
        
        if ($this->isPasswordValid($hash, $password) === false) {
            throw new AuthenticationFailedError($this, 'Bad credentials.', self::ERROR_CODE);
        }
        
        $user = $this->getUserFromToken($token);
        $this->logSuccessfulAuthentication($user, $token->getUsername());
        $this->authenticatedToken = $token;
        $this->syncUserRoles($user, $token);
        
        return $token;
    }
    
    /**
     * Checks a plain password against a hash created by `password_hash()` or libsodium.
     * 
     * Mirrors the former Symfony NativePasswordEncoder: rejects overly long passwords (DoS protection)
     * and passwords longer than 72 bytes for bcrypt hashes, because bcrypt silently truncates them.
     * 
     * @param string $hash
     * @param string $password
     * @return bool
     */
    protected function isPasswordValid(string $hash, string $password) : bool
    {
        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            return false;
        }
        if (strpos($hash, '$argon') !== 0) {
            return (strlen($password) <= self::BCRYPT_MAX_PASSWORD_LENGTH || strpos($hash, '$2') !== 0) 
                && password_verify($password, $hash);
        }
        if (extension_loaded('sodium') && version_compare(SODIUM_LIBRARY_VERSION, '1.0.14', '>=')) {
            return sodium_crypto_pwhash_str_verify($hash, $password);
        }
        return password_verify($password, $hash);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        return $this->authenticatedToken === $token;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.SIGN_IN');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool {
        return ($token instanceof MetamodelUsernamePasswordAuthToken) && $this->isSupportedFacade($token);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::createLoginForm()
     */
    protected function createLoginForm(Form $container) : Form
    {
        $container->setWidgets(new UxonObject([
            [
                'attribute_alias' => 'USERNAME',
                'required' => true
            ],[
                'attribute_alias' => 'PASSWORD',
                'required' => true
            ],[
                'attribute_alias' => 'AUTH_TOKEN_CLASS',
                'value' => '\\' . MetamodelUsernamePasswordAuthToken::class,
                'widget_type' => 'InputHidden'
            ]
        ]));
        
        $container->setColumnsInGrid(1);
        
        if ($container->hasButtons() === false) {
            $container->addButton($container->createButton(new UxonObject([
                'action_alias' => 'exface.Core.Login',
                'align' => EXF_ALIGN_OPPOSITE,
                'visibility' => WidgetVisibilityDataType::PROMOTED
            ])));
        }
        
        return $container;
    }
}