<?php
namespace exface\Core\Exceptions\Selectors;

use exface\Core\Exceptions\UnexpectedValueException;

/**
 * Exception thrown if a selector is invalid (e.g. syntax errors, unknown selector type etc.).
 * 
 * @author Andrej Kabachnik
 *
 */
class SelectorInvalidError extends UnexpectedValueException
{
    
}