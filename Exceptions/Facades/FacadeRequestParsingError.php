<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if the facade fails to read data from the current HTTP request.
 *
 * @author Andrej Kabachnik
 *        
 */
class FacadeRequestParsingError extends RuntimeException
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode(int $default = 400) : int
    {
        return parent::getStatusCode($default);
    }
}