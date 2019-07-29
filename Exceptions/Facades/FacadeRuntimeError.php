<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Exceptions\LogicException;

/**
 * Exception thrown on errors in the facade logic (i.e. tempate element missing mandatory properties).
 *
 * @author Andrej Kabachnik
 *        
 */
class FacadeRuntimeError extends LogicException
{
}