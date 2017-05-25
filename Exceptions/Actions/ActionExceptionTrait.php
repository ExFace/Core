<?php
namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Exceptions\ExceptionTrait;

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
     *
     * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::getAction()
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ActionExceptionInterface::setAction()
     */
    public function setAction(ActionInterface $value)
    {
        $this->action = $value;
        return $this;
    }
}
?>