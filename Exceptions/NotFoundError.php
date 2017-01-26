<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Base class for all kinds of not-found-errors:
 * @see FileNotFoundError
 * @see DirectoryNotFoundError
 *
 * @author Andrej Kabachnik
 *
 */
abstract class NotFoundError extends RuntimeException implements ErrorExceptionInterface {
	
}
?>