<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if the requested app cannot be found (i.e.
 * class not found).
 *
 * Normally this happens if the app alias is misspelled in UXON.
 *
 * @author Andrej Kabachnik
 *        
 */
class AppNotFoundError extends OutOfRangeException implements ErrorExceptionInterface
{
}
?>