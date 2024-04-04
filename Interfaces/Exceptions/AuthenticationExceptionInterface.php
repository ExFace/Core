<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Security\AuthenticationProviderInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;

Interface AuthenticationExceptionInterface extends SecurityExceptionInterface
{
    /**
     * 
     * @return AuthenticationProviderInterface
     */
    public function getAuthenticationProvider() : AuthenticationProviderInterface;
    
    /**
     * 
     * @return AuthenticationTokenInterface|NULL
     */
    public function getAuthenticationToken() : ?AuthenticationTokenInterface;
}