<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface;

/**
 * Exception thrown if an authentication attempt fails
 *
 * @author Andrej Kabachnik
 *        
 */
class AuthenticationFailedError extends RuntimeException implements AuthenticationExceptionInterface
{
    private $authErrors = [];
    
    public function addAuthenticatorError(AuthenticatorInterface $authenticator, AuthenticationExceptionInterface $exception) : self
    {
        $this->authErrors[] = [
            'authenticator' => $authenticator,
            'exception' => $exception
        ];
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode()
    {
        return 403;
    }
}