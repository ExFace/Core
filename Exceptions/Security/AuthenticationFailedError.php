<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface;
use exface\Core\Interfaces\Security\AuthenticationProviderInterface;
use exface\Core\Events\Security\OnAuthenticationFailedEvent;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if an authentication attempt fails
 *
 * @author Andrej Kabachnik
 *        
 */
class AuthenticationFailedError extends RuntimeException implements AuthenticationExceptionInterface
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
        $authProvider->getWorkbench()->eventManager()->dispatch(new OnAuthenticationFailedEvent($authProvider->getWorkbench(), $this));
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
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ERROR;
    }
}