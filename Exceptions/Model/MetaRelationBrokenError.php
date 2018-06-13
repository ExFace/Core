<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if a relation cannot be correctly instatiated.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaRelationBrokenError extends RuntimeException implements MetaObjectExceptionInterface
{
    
    use MetaObjectExceptionTrait;

    /**
     *
     * @param MetaObjectInterface $meta_object            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(MetaObjectInterface $relation_left_object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMetaObject($relation_left_object);
    }
}
?>