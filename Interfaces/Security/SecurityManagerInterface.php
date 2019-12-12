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