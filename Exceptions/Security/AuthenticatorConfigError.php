<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface;
use exface\Core\Interfaces\Security\AuthenticationProviderInterface;

/**
 * Exception thrown if an authentication provider was improperly configured
 *
 * @author Andrej Kabachnik
 *        
 */
class AuthenticatorConfigError extends RuntimeException implements AuthenticationExceptionInterface
{
    private $provider = null;
    
    /**
     * 
     * @param AuthenticationProviderInterface $authProvider
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     * 
     * @triggers \exface\Core\Events\Security\OnAuthenticationFailedEvent
     * 
     */
    public function __construct(AuthenticationProviderInterface $authProvider, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->provider = $authProvider;
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
}