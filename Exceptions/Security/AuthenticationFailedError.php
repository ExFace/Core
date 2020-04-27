<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface;
use exface\Core\Interfaces\Security\AuthenticationProviderInterface;

/**
 * Exception thrown if an authentication attempt fails
 *
 * @author Andrej Kabachnik
 *        
 */
class AuthenticationFailedError extends RuntimeException implements AuthenticationExceptionInterface
{
    private $authErrors = [];
    
    private $provider = null;
    
    /**
     * 
     * @param AuthenticationProviderInterface $authProvider
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     * @param AuthenticationExceptionInterface[] $nestedAuthenticatorErrors
     */
    public function __construct(AuthenticationProviderInterface $authProvider, $message, $alias = null, $previous = null, array $nestedAuthenticatorErrors = [])
    {
        parent::__construct($message, $alias, $previous);
        $this->provider = $authProvider;
        $this->authErrors = $nestedAuthenticatorErrors;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode()
    {
        return 401;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface::getAuthenticationProvider()
     */
    public function getAuthenticationProvider() : AuthenticationProviderInterface
    {
        return $this->provider;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7AL3G5P';
    }
    
    /**
     * 
     * @return AuthenticationExceptionInterface[]
     */
    public function getNestedAuthenticatorErrors() : array
    {
        return $this->authErrors;
    }
}