<?php
namespace exface\Core\Interfaces;

interface UserImpersonationInterface
{
    /**
     * Returns the username of the user.
     * 
     * @return string|NULL
     */
    public function getUsername() : ?string;
    
    /**
     * Returns TRUE if it is an anonymous user.
     * 
     * Note, that depending on the configuration of the authorization subsystem
     * anonymous users may even have usernames!
     *
     * @return bool
     */
    public function isAnonymous() : bool;
}