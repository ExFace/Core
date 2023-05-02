<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * Exception thrown if an authentication attempt requires additional information - e.g. a second factor
 *
 * @author Andrej Kabachnik
 *        
 */
class AuthenticationIncompleteError extends AuthenticationFailedError
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7QCK0MD';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::INFO;
    }
}