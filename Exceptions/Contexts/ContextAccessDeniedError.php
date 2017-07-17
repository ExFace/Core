<?php
namespace exface\Core\Exceptions\Contexts;

use exface\Core\Exceptions\SecurityException;
use exface\Core\Interfaces\Exceptions\ContextExceptionInterface;

/**
 * Exception thrown if the current user has no access to a requested context.
 * 
 * See error code 6VYMG0N for details
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextAccessDeniedError extends SecurityException implements ContextExceptionInterface
{
    use ContextExceptionTrait;
    
    public function getDefaultAlias()
    {
        return '6VYMG0N';
    }
}