<?php
namespace exface\Core\Exceptions\Templates;

use exface\Core\Exceptions\LogicException;

/**
 * Exception thrown on errors in the template logic (i.e. tempate element missing mandatory properties).
 *
 * @author Andrej Kabachnik
 *        
 */
class TemplateLogicError extends LogicException
{
}