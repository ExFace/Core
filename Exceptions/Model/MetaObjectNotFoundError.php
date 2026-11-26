<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;

/**
 * Exception thrown if a meta object cannot be found in the model.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaObjectNotFoundError extends UnexpectedValueException implements NotFoundExceptionInterface
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\UnexpectedValueException::getDefaultAlias()
     */
    public function getDefaultAlias(){
        return '6VG359M';
    }
}