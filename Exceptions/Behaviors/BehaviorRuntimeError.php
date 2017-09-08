<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\Model\MetaObjectExceptionTrait;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\RuntimeException;

/**
 * Exception thrown if a behavior experiences an error at runtime (e.g.
 * not detectable at compile time).
 *
 * @author Andrej Kabachnik
 *        
 */
class BehaviorRuntimeError extends RuntimeException
{
    
    use MetaObjectExceptionTrait;

    /**
     *
     * @param MetaObjectInterface $meta_object            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(MetaObjectInterface $meta_object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMetaObject($meta_object);
    }
}
?>