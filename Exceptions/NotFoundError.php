<?php
namespace exface\Core\Exceptions;

/**
 * Base class for all kinds of not-found-errors:
 * @see FileNotFoundError
 * @see DirectoryNotFoundError
 *
 * @author Andrej Kabachnik
 *
 */
abstract class NotFoundError extends OutOfBoundsException {
	
}
?>