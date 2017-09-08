<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\Model\MetaObjectExceptionTrait;

/**
 * Exception thrown if an object's behavior could not be loaded (i.e.
 * class not found)
 *
 * @author Andrej Kabachnik
 *        
 */
class BehaviorNotFoundError extends UnexpectedValueException implements MetaObjectExceptionInterface
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