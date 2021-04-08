<?php
namespace exface\Core\Events\Action;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Events\TaskEventInterface;
use exface\Core\Interfaces\Events\ErrorEventInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;

/**
 * Event fired after an action caused an exception.
 * 
 * @event exface.Core.Action.OnActionException
 *
 * @author Ralf Mulansky
 *        
 */
class OnActionFailedEvent extends AbstractActionEvent implements TaskEventInterface, ErrorEventInterface
{
    private $exception = null;
    
    private $task = null;
    
    private $transaction = null;
    
    /**
     * 
     * @param ActionInterface $action
     * @param ResultInterface $error
     * @param DataTransactionInterface $transaction
     */
    public function __construct(ActionInterface $action, TaskInterface $task, \Throwable $exception, DataTransactionInterface $transaction)
    {
        parent::__construct($action);
        $this->task = $task;
        if (! $exception instanceof ErrorEventInterface) {
            $exception = new InternalError($exception->getMessage(), null, $exception);
        }
        $this->exception = $exception;
        $this->transaction = $transaction;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\TaskEventInterface::getTask()
     */
    public function getTask() : TaskInterface
    {
        return $this->task;
    }
    
    /**
     * 
     * @return DataTransactionInterface
     */
    public function getTransaction() : DataTransactionInterface
    {
        return $this->transaction;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\ErrorEventInterface::getException()
     */
    public function getException(): ExceptionInterface
    {
        return $this->exception;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Action.OnActionFailed';
    }
    

}