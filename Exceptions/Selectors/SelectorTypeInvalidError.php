<?php
namespace exface\Core\Exceptions\Selectors;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if a method of a selector is called, that is incompatible with the current selector type.
 * 
 * E.g. if getAlias() is called on a UID-based selector
 * 
 * @author Andrej Kabachnik
 *
 */
class SelectorTypeInvalidError extends RuntimeException
{
    
}