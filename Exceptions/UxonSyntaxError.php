<?php
namespace exface\Core\Exceptions;

/**
 * Exception thrown if a JSON string cannot be parsed into a UXON object.
 *
 * @author Andrej Kabachnik
 *        
 */
class UxonSyntaxError extends InvalidArgumentException
{}