<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if two password does not match.
 *
 * @author Andrej Kabachnik
 *        
 */
class PasswordMismatchError extends RuntimeException
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7A8LNGD';
    }    
}