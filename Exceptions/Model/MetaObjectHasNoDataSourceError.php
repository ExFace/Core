<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;

/**
 * Exception thrown if a meta object does not have a data source, but the current operation requires it.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaObjectHasNoDataSourceError extends RuntimeException implements MetaObjectExceptionInterface
{
    
    use MetaObjectExceptionTrait;

    public function getDefaultAlias()
    {
        return '6ZIT36X';
    }
}
?>