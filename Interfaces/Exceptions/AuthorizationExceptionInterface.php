<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\Security\PermissionInterface;

Interface AuthorizationExceptionInterface extends SecurityExceptionInterface
{
    /**
     * 
     * @return AuthorizationPointInterface
     */
    public function getAuthorizationPoint() : AuthorizationPointInterface;
    
    /**
     * 
     * @return PermissionInterface
     */
    public function getPermission() : PermissionInterface;
}