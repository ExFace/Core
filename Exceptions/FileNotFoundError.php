<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if an file was not found.
 *
 * @author Andrej Kabachnik
 *
 */
class FileNotFoundError extends NotFoundError implements ErrorExceptionInterface {
	
}
?>