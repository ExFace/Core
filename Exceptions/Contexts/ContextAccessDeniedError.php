<?php
namespace exface\Core\Exceptions\Contexts;

use exface\Core\Interfaces\Exceptions\ContextExceptionInterface;
use exface\Core\Exceptions\Security\AccessPermissionDeniedError;

/**
 * Exception thrown if the current user has no access to a requested context.
 * 
 * See error code 6VYMG0N for details
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextAccessDeniedError extends AccessPermissionDeniedError implements ContextExceptionInterface
{
    public function getDefaultAlias()
    {
        return '6VYMG0N';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ContextExceptionInterface::getContext()
     */
    public function getContext()
    {
        return $this->getObject();
    }
}