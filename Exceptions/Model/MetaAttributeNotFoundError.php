<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Exceptions\MetaObjectExceptionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Exception thrown if a requested attribute cannot be found for the given object.
 * This will mostly happen if
 * an attribute alias is misspelled in UXON.
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaAttributeNotFoundError extends UnexpectedValueException implements MetaObjectExceptionInterface
{
    use MetaObjectExceptionTrait;
    
    private ?string $attrAlias = null;

    /**
     *
     * @param MetaObjectInterface $meta_object
     * @param string $message
     * @param null $alias
     * @param null $previous
     * @param string|null $aliasWithRelationPath
     */
    public function __construct(MetaObjectInterface $meta_object, $message, $alias = null, $previous = null, ?string $aliasWithRelationPath = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMetaObject($meta_object);
        $this->attrAlias = $aliasWithRelationPath;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\UnexpectedValueException::getDefaultAlias()
     */
    public function getDefaultAlias(){
        return '6VG35OA';
    }

    /**
     * @return string|null
     */
    public function getAttributeAliasWithRelationPath() : ?string
    {
        return $this->attrAlias;
    }
}