<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\Selectors\AuthorizationPointSelector;

/**
 * Interface for the central security provider for the workbench.
 * 
 * @author Andrej Kabachnik
 *
 */
interface SecurityManagerInterface extends WorkbenchDependantInterface, AuthenticatorInterface
{
    /**
     * Returns the currently valid authentication token.
     * 
     * Use this method to get the username and other authentication-related data 
     * as it does not produce the overhead of loading the entire user model like
     * `getAuthenticatedUser()`.
     * 
     * @return AuthenticationTokenInterface
     */
    public function getAuthenticatedToken() : AuthenticationTokenInterface;
    
    /**
     * Returns the currently authenticated user.
     * 
     * @return UserInterface
     */
    public function getAuthenticatedUser() : UserInterface;
    
    /**
     * Returns the user model for the given authentication token. 
     * 
     * @param AuthenticationTokenInterface $token
     * @return UserInterface
     */
    public function getUser(AuthenticationTokenInterface $token) : UserInterface;
    
    /**
     * Returns the authorization point specified by the given selector.
     * 
     * The security manager keeps a central repository of authorization points and makes sure
     * their configuration is only loaded once. 
     * 
     * To authorize against an authorization point use something like this:
     * 
     * ```
     * $workbench->getSecurity()->getAuthorizationPoint(UiPageAuthorizationPoint::class)->authorize();
     * 
     * ```
     * 
     * @param AuthorizationPointSelector|string $selectorOrString
     * @return AuthorizationPointInterface
     */
    public function getAuthorizationPoint(string $selectorOrString) : AuthorizationPointInterface;
}