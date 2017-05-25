<?php
namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Actions\ActionExceptionTrait;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Exceptions\ActionExceptionInterface;

/**
 * Exception thrown if an action fails to read it's configuration or an invalid configuration value is passed.
 *
 * This exception will be typically thrown by setters in the action class. This way, configuration values being
 * set programmatically and via UXON import can be checked in the same manner.
 *
 * @author Andrej Kabachnik
 *        
 */
class ActionConfigurationError extends UnexpectedValueException implements ActionExceptionInterface, ErrorExceptionInterface
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
