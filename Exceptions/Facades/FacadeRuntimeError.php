<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown on rendering errors in the facade: e.g ambiguous element ids, etc..
 *
 * @author Andrej Kabachnik
 *        
 */
class FacadeRuntimeError extends RuntimeException
{
}