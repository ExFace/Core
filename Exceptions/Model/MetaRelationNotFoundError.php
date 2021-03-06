<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Exceptions\MetaRelationResolverExceptionInterface;

/**
 * Exception thrown if a requested relation cannot be found for the given meta object.
 * This will mostly happen if
 * a relation path is misspelled in UXON.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaRelationNotFoundError extends UnexpectedValueException implements MetaRelationResolverExceptionInterface
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