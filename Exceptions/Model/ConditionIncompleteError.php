<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if a condition cannot be create because it lacks an expression on one of the sides.
 *
 * @author Andrej Kabachnik
 *        
 */
class ConditionIncompleteError extends RuntimeException
{
}