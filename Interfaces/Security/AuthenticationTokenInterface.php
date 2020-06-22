<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\UserImpersonationInterface;

/**
 * Tokens store authentication information and can be authenticated by whe workbench security.
 * 
 * Different token classes can be used for differet authentication types: username/password,
 * LDAP, REMOTE_USER, API token, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface AuthenticationTokenInterface extends UserImpersonationInterface
{    
    /**
     * Returns the facade, that the user uses to interact with the workbench (if available).
     * 
     * @return FacadeInterface|NULL
     */
    public function getFacade() : ?FacadeInterface;
}