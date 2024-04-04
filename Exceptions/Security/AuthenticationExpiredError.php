<?php
namespace exface\Core\Exceptions\Security;

/**
 * Exception thrown if an authentication attempt fails
 *
 * @author Andrej Kabachnik
 *        
 */
class AuthenticationExpiredError extends AuthenticationFailedError
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\Security\AuthenticationFailedError::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7NHEM6W';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\Security\AuthenticationFailedError::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return $this->getAuthenticationProvider()->getWorkbench()->getConfig()->getOption('DEBUG.LOG_LEVEL_AUTHENTICATION_EXPIRED');
    }
}