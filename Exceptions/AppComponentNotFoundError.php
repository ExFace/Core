<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;
use Psr\Container\NotFoundExceptionInterface as PsrNotFoundExceptionInterface;

/**
 * Exception thrown if an app component was not found.
 *
 * Typically, this happens if a selector is passed to AppInterface::get(), that
 * cannot be interpreted by the app.
 *
 * @author Andrej Kabachnik
 *        
 */
class AppComponentNotFoundError extends OutOfRangeException implements ErrorExceptionInterface, NotFoundExceptionInterface, PsrNotFoundExceptionInterface
{
}
?>