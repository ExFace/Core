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
}