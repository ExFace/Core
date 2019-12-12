<?php
namespace exface\Core\Interfaces\Security;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Tokens store authentication information and can be authenticated by whe workbench security.
 * 
 * Different token classes can be used for differet authentication types: username/password,
 * LDAP, REMOTE_USER, API token, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface AuthenticationTokenInterface
{
    /**
     * 
     * @return UserInterface
     */
    public function getUser() : UserInterface;
    
    /**
     * 
     * @return string|NULL
     */
    public function getUsername() : ?string;
    
    /**
     * Returns the facade, that the user uses to interact with the workbench (if available).
     * 
     * @return FacadeInterface|NULL
     */
    public function getFacade() : ?FacadeInterface;
    
    /**
     * Returns TRUE if the token represents an anonymous user.
     * 
     * This is important as different authorizantion providers treat anonymous users
     * differently. Some may even have a username for the anonymous user!
     * 
     * @return bool
     */
    public function isAnonymous() : bool;
}