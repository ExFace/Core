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
    public function getStatusCode()
    {
        return 403;
    }
}