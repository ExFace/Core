<?php
namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\Actions\ActionExceptionTrait;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Exceptions\ActionExceptionInterface;
use exface\Core\Exceptions\RuntimeException;

/**
 * Exception for errors during exporting data.
 * 
 * @author SFL
 *
 */
class ActionExportDataError extends RuntimeException implements ActionExceptionInterface, ErrorExceptionInterface
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
