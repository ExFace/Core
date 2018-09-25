<?php
namespace exface\Core\Events\Action;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Events\TaskEventInterface;
use exface\Core\Interfaces\Events\ResultEventInterface;

/**
 * Event fired after an action is performed but before the transaction is autocommitted.
 * 
 * @event exface.Core.Action.OnHandleTask
 *
 * @author Andrej Kabachnik
 *        
 */
class OnHandleTaskEvent extends AbstractActionEvent implements TaskEventInterface, ResultEventInterface
{
    private $result = null;
    
    private $transaction = null;
    
    /**
     * 
     * @param ActionInterface $action
     * @param ResultInterface $result
     * @param DataTransactionInterface $transaction
     */
    public function __construct(ActionInterface $action, ResultInterface $result, DataTransactionInterface $transaction)
    {
        parent::__construct($action);
        $this->result = $result;
        $this->transaction = $transaction;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\TaskEventInterface::getTask()
     */
    public function getTask() : TaskInterface
    {
        return $this->result->getTask();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\ResultEventInterface::getResult()
     */
    public function getResult() : ResultInterface
    {
        return $this->result;
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