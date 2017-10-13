<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;

/**
 * Exception thrown if a valud cannot be parsed by the respective data type class.
 *
 * This Exception should be thrown on errors in the DataType::cast() methods.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTypeValidationError extends RangeException implements ErrorExceptionInterface
{
}
?>