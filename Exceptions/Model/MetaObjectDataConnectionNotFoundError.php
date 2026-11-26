<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\OutOfBoundsException;
use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;

/**
 * Exception thrown if no valid data connection can be found for a meta object.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaObjectDataConnectionNotFoundError extends OutOfBoundsException implements NotFoundExceptionInterface
{
}