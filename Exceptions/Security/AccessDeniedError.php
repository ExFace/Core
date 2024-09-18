<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if authorization fails on an authorization point
 *
 * @author Andrej Kabachnik
 *        
 */
class AccessDeniedError extends RuntimeException
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode(int $default = 403) : int
    {
        return parent::getStatusCode($default);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7ATP376';
    }
}