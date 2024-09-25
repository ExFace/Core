<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if the routing to a facade or within it fails.
 *
 * @author Andrej Kabachnik
 *        
 */
class FacadeRoutingError extends RuntimeException
{
    public function getStatusCode(int $default = 404) : int
    {
        return parent::getStatusCode($default);
    }
}