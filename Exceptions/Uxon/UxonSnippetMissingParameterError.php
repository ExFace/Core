<?php
namespace exface\Core\Exceptions\Uxon;

use exface\Core\Exceptions\InvalidArgumentException;

/**
 * Exception thrown when a required parameter of a UXON snippet is missing
 * 
 * @author Andrej Kabachnik
 */
class UxonSnippetMissingParameterError extends InvalidArgumentException
{
}