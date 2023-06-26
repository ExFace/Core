<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\CliEnvAuthToken;
use exface\Core\CommonLogic\Security\Authenticators\Traits\CreateUserFromTokenTrait;
use exface\Core\Facades\ConsoleFacade;
use exface\Core\CommonLogic\Security\AuthenticationToken\RememberMeAuthToken;

/**
 * Performs authentication for php scripts run in cli environment. 
 * 
 * ## Examples
 * 
 * ### Authentication + create new users with static roles
 * 
 * ```
 * {
 * 		"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\CliAuthenticator",
 * 		"create_new_users": true,
 * 		"create_new_users_with_roles": [
 * 			"exface.Core.SUPERUSER"
 * 		]
 * }
 * 
 * ```
 * 
 * If `create_new_users` is `true`, a new workbench user will be created automatically once
 * the authentication was successful. These new users can be assigned some roles
 * under `create_new_users_with_roles`. The user will be created without a password.
 * 
 * If a new user is not assigned any roles, he or she will only have access to resources
 * available for the user roles `exface.Core.ANONYMOUS` and `exface.Core.AUTHENTICATED`.
 * 
 * @author Andrej Kabachnik
 *
 */
class CliAuthenticator extends AbstractAuthenticator
{    
    use CreateUserFromTokenTrait;
    
    private $authenticatedToken = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        if (! ($token instanceof CliEnvAuthToken)) {
            throw new AuthenticationFailedError($this, 'Invalid token type!');
        }        
        
        if (ConsoleFacade::isPhpScriptRunInCli() === false) {
            throw new AuthenticationFailedError($this, "Authenticator '{$this->getName()}' can only be used to authenticate when php scripts are run from command line!");
        }
        
        $this->checkAuthenticatorDisabledForUsername($token->getUsername());
        
        $currentUsername = (new CliEnvAuthToken())->getUsername();
        if ($token->getUsername() !== $currentUsername) {
            throw new AuthenticationFailedError($this, "Cannot authenticate user '{$token->getUsername()}' via '{$this->getName()}'");
        }
        
        if ($token->isAnonymous() === false) {
            $user = null;
            if ($this->userExists($token) === true) {
                $user = $this->getUserFromToken($token);
            } elseif ($this->getCreateNewUsers(true) === true) {
                $user = $this->createUserWithRoles($this->getWorkbench(), $token);
                //second authentication to save credentials
            } else {
                throw new AuthenticationFailedError($this, "Authentication failed, no workbench user '{$token->getUsername()}' exists: either create one manually or enable `create_new_users` in authenticator configuration!", '7AL3J9X');
            }
            if ($token->getUsername() !== $user->getUsername()) {
                return new RememberMeAuthToken($user->getUsername());
            }
            $this->logSuccessfulAuthentication($user, $token->getUsername());
        }
        
        $this->authenticatedToken = $token;
        
        $this->syncUserRoles($user, $token);
        
        return $token;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        return $token === $this->authenticatedToken;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool
    {
        return ($token instanceof CliEnvAuthToken) && $this->isSupportedFacade($token);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return 'Command line authentication';
    }
}