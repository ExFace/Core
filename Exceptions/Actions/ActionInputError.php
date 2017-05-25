<?php
namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Actions\ActionExceptionTrait;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Exceptions\ActionExceptionInterface;

/**
 * Exception thrown if an action receives unexpected input values (e.g.
 * in the input data sheet).
 *
 * It's the base class for more specific excpetions:
 *
 * @see ActionInputInvalidObjectError
 * @see ActionInputMissingError
 *
 * @author Andrej Kabachnik
 *        
 */
class ActionInputError extends UnexpectedValueException implements ActionExceptionInterface, ErrorExceptionInterface
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
