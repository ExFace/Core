<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown if an app component was not found.
 *
 * Typically, this happens if a selector is passed to AppInterface::get(), that
 * cannot be interpreted by the app.
 *
 * @author Andrej Kabachnik
 *        
 */
class AppComponentFoundError extends OutOfRangeException implements ErrorExceptionInterface, NotFoundExceptionInterface
{
}
?>