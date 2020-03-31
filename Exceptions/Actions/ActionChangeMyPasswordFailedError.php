<?php
namespace exface\Core\Exceptions\Actions;

/**
 * Exception thrown an action cannot be undone.
 *
 * @author Andrej Kabachnik
 *        
 */
class ActionChangeMyPasswordFailedError extends ActionRuntimeError
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '7A8LNGD';
    }
}
