<?php
namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Actions\ActionExceptionTrait;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Exceptions\ActionExceptionInterface;

/**
 * Exception should be used in actions instead of a simple LogicException, because it will output/log
 * much more usefull information about the error including the actions UXON representation, input data,
 * etc.
 *
 * @see LogicException
 *
 * @author Andrej Kabachnik
 *        
 */
class ActionLogicError extends LogicException implements ActionExceptionInterface, ErrorExceptionInterface
{
    
    use ActionExceptionTrait;

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
}
