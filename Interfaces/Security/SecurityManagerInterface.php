<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

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
}