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
}