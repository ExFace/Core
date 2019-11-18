<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Exceptions\LogicException;

/**
 * Exception thrown on errors in the facade logic: e.g. tempate element missing mandatory properties, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class FacadeLogicError extends LogicException
{
}