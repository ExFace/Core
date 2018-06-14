<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;

/**
 * Exception thrown if a requested relation alias matches more than one relation.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaRelationAliasAmbiguousError extends UnexpectedValueException implements MetaObjectExceptionInterface
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