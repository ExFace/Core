<?php

namespace exface\Core\Exceptions;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if resource is not available.
 *
 * @author jsc
 *
 */
class UnavailableError extends RuntimeException
{
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode(int $default = 503) : int
    {
        return parent::getStatusCode($default);
    }
}
