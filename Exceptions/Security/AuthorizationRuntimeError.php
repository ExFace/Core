<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown on errors in authorization logic.
 * 
 * These exceptions are typically not visible to the user as the authorization exceptions
 * should generally result in `Indeterminate` permissions instead of visible errors. However,
 * these exceptions are visible in the logs as they mostly occur on errors, that should be
 * fixed.
 *
 * @author Andrej Kabachnik
 *        
 */
class AuthorizationRuntimeError extends RuntimeException
{
    
}