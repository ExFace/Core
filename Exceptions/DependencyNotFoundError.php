<?php

namespace exface\Core\Exceptions;

/**
 * Exception thrown if a dependency was not found.
 * This is especially usefull for JavaScript dependencies in templates.
 *
 * @author Andrej Kabachnik
 *        
 */
class DependencyNotFoundError extends LogicException
{
}