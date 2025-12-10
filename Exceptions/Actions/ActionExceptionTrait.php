<?php
namespace exface\Core\Exceptions\Actions;

use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Interfaces\Exceptions\ActionExceptionInterface;
use exface\Core\Widgets\DebugMessage;

/**
 * This trait enables an exception to output action specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait ActionExceptionTrait {
    
    use ExceptionTrait {
		createWidget as createParentWidget;
	}

    private $action = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::__construct()
     */
    public function __construct(ActionInterface $action, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setAction($action);
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::getAction()
     */
    public function getAction() : ActionInterface
    {
        return $this->action;
    }

    /**
     * @param ActionInterface $value
     * @return $this
     */
    protected function setAction(ActionInterface $value) : ActionExceptionInterface
    {
        $this->action = $value;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message = parent::createDebugWidget($error_message);
        return $this->getAction()->createDebugWidget($error_message);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getLinks()
     */
    public function getLinks() : array
    {
        $links = parent::getLinks();
        $action = $this->getAction();
        $prototypeClass = get_class($action);
        $links['Action prototype `' . $prototypeClass . '`'] = DocsFacade::buildUrlToDocsForUxonPrototype($prototypeClass);
        try {
            $obj = $action->getMetaObject();
            $links['Metaobject ' . $obj->__toString()] = DocsFacade::buildUrlToDocsForMetaObject($obj->getAliasWithNamespace());
        } catch (\Throwable $e) {
            // Do nothing - we just can't link an object if there is none
        }
        return $links;
    }
}