<?php
namespace exface\Core\Exceptions;

/**
 * Exception thrown if a non-ExFace exception is caught. 
 * 
 * This is a wrapper for unknown exception types. It enables all exceptions
 * to produce debug widgets.
 *
 * @author Andrej Kabachnik
 *        
 */
class InternalError extends LogicException
{
}
?>