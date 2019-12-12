<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;

/**
 * Authenticators can authenticate auth-tokens in different ways.
 * 
 * There can be any number of authenticators in a workbench. Each is responsible
 * for processing tokens, that it supports - see `isSupported()`. The `SecurityManager`
 * iterates over all active authenticators and tries to `authenticate()` the token.
 * 
 * An authenticator also must recognize a token, that it previously authenticated
 * to allow other components to validate if the token is still active.
 * 
 * @author Andrej Kabachnik
 *
 */
interface AuthenticatorInterface extends WorkbenchDependantInterface
{
    /**
     * Authenticators must be instantiatable with just the workbench as argument
     * because custom authenticators can be listed in the system config an there
     * is no way to define other arguments there.
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench);
    
    /**
     * Authenticates the given token or throws an exception.
     * 
     * @param AuthenticationTokenInterface $token
     * @throws AuthenticationFailedError
     * @return AuthenticationTokenInterface
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface;
    
    /**
     * Checks if the given token is still authenticated.
     * 
     * @param AuthenticationTokenInterface $token
     * @return bool
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool;
    
    /**
     * Returns TRUE if the authenticator can deal with the given token and FALSE otherwise.
     * 
     * @param AuthenticationTokenInterface $token
     * @return bool
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool;
    
    /**
     * Returns a human-friendly name of the authenticator for error messages, etc.
     * 
     * @return string
     */
    public function getName() : string;
}