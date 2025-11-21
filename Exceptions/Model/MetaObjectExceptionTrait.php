<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\ExceptionTrait;

/**
 * This trait enables an exception to output meta object specific debug information: properties, attributes, behaviors, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
trait MetaObjectExceptionTrait 
{
    use ExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $meta_object = null;

    public function __construct(MetaObjectInterface $meta_object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMetaObject($meta_object);
    }

    /**
     *
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->meta_object;
    }

    /**
     *
     * @param MetaObjectInterface $object            
     * @return \exface\Core\Exceptions\Model\MetaObjectExceptionTrait
     */
    protected function setMetaObject(MetaObjectInterface $object)
    {
        $this->meta_object = $object;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getLinks()
     */
    public function getLinks() : array
    {
        $links = parent::getLinks();
        $obj = $this->getMetaObject();
        $links['Metaobject ' . $obj->__toString()] = DocsFacade::buildUrlToDocsForMetaObject($obj->__toString());
        return $links;
    }
}