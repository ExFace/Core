<?php
namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;

/**
 * Exception thrown if a context expects certain data to be available in it's scope and this data is not here.
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextDataNotFoundError extends ContextRuntimeError implements NotFoundExceptionInterface
{
}