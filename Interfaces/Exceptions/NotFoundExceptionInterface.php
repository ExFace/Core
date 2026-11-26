<?php
namespace exface\Core\Interfaces\Exceptions;

/**
 * Indicates that a lookup did not find the requested resource.
 *
 * Do not use this for invalid values or failures that prevented the lookup from completing.
 * 
 * @author Andrej Kabachnik
 */
interface NotFoundExceptionInterface extends ExceptionInterface
{
}