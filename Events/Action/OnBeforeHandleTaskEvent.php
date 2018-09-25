<?php
namespace exface\Core\Events\Action;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\TaskEventInterface;

/**
 * Event fired before an action is performed.
 *
 * @event exface.Core.Action.OnBeforeHandleTask
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeHandleTaskEvent extends AbstractActionEvent implements TaskEventInterface
{
    private $task = null;
    
    private $transaction = null;
    
    /**
     * 
     * @param ActionInterface $action
     * @param TaskInterface $task
     * @param DataTransactionInterface $transaction
     */
    public function __construct(ActionInterface $action, TaskInterface $task, DataTransactionInterface $transaction)
    {
        parent::__construct($action);
        $this->task = $task;
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
}