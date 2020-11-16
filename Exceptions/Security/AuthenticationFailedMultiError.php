<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface;
use exface\Core\Interfaces\Security\AuthenticationProviderInterface;

/**
 * Exception thrown if a multi-provider authentication attempt fails
 *
 * @author Andrej Kabachnik
 *        
 */
class AuthenticationFailedMultiError extends RuntimeException implements AuthenticationExceptionInterface
{
    private $authErrors = [];
    
    private $provider = null;
    
    /**
     * 
     * @param AuthenticationProviderInterface $authProvider
     * @param string $message
     * @param string $alias
     * @param AuthenticationExceptionInterface[] $nestedAuthenticatorErrors
     */
    public function __construct(AuthenticationProviderInterface $authProvider, $message, $alias = null, array $nestedAuthenticatorErrors = [])
    {
        parent::__construct($message, $alias);
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