<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Security\AuthenticationProviderInterface;

Interface AuthenticationExceptionInterface extends SecurityExceptionInterface
{
    /**
     * 
     * @return AuthenticationProviderInterface
     */
    public function getAuthenticationProvider() : AuthenticationProviderInterface;
}