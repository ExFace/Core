<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;

/**
 * Is thrown if an Exface user is requested that doesn't exist.
 * 
 * @author SFL
 *
 */
class UserNotFoundError extends UserException implements NotFoundExceptionInterface
{
}
