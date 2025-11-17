<?php
namespace exface\Core\Exceptions\Behaviors;

use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\Model\MetaObjectExceptionTrait;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Exceptions\BehaviorExceptionInterface;

/**
 * 
 * @author andrej.kabachnik
 *
 */
abstract class AbstractBehaviorException extends UnexpectedValueException implements BehaviorExceptionInterface
{
    use MetaObjectExceptionTrait;
    
    private $behavior = null;

    /**
     *
     * @param BehaviorInterface $behavior            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(BehaviorInterface $behavior, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->behavior = $behavior;
        $this->setAlias($alias);
        $this->setMetaObject($behavior->getObject());
    }
    
    public function getBehavior() : BehaviorInterface
    {
        return $this->behavior;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getLinks()
     */
    public function getLinks() : array
    {
        $links = parent::getLinks();
        $behavior = $this->getBehavior();
        $links['Behavior prototype `' . $behavior->getAliasWithNamespace() . '`'] = DocsFacade::buildUrlToDocsForUxonPrototype(get_class($behavior));
        $links['Metaobject ' . $behavior->getObject()->__toString()] = DocsFacade::buildUrlToDocsForMetaObject($behavior->getObject()->getAliasWithNamespace());
        return $links;
    }
}