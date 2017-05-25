<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;

/**
 * Exception thrown if inconsistencies in the meta model of an object are detected.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaObjectModelError extends RuntimeException implements MetaObjectExceptionInterface
{
    
    use MetaObjectExceptionTrait;

    public static function getDefaultAlias()
    {
        return '6VCYA0J';
    }
}
?>