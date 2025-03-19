<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Exception thrown if a requested attribute group cannot be found for the given object.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaAttributeGroupNotFoundError extends UnexpectedValueException implements MetaObjectExceptionInterface
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