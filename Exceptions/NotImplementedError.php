<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown some implementation is missing. This should only represent a temporary condition!
 *
 * @author Andrej Kabachnik
 *
 */
class NotImplementedError extends LogicException implements ErrorExceptionInterface {
	
}
?>