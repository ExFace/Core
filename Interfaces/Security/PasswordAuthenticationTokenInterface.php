<?php
namespace exface\Core\Interfaces\Security;

/**
 * Interface for password-based authentication tokens
 * 
 * @author Andrej Kabachnik
 *
 */
interface PasswordAuthenticationTokenInterface extends AuthenticationTokenInterface
{    
    public function getPassword() : string;
}