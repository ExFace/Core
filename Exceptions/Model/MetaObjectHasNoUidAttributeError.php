<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;

/**
 * Exception thrown if a meta object does not have a UID attribute, but the current runtime requires it.
 *
 * In general, an object does not neccessarily need a UID attribute. However, certain actions or query builders
 * require it for their logic to function correctly.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaObjectHasNoUidAttributeError extends RuntimeException implements MetaObjectExceptionInterface
{
    
    use MetaObjectExceptionTrait;

    public function getDefaultAlias()
    {
        return '6UX6TNT';
    }
}
?>