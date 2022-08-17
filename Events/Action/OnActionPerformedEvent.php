<?php
namespace exface\Core\Events\Action;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Events\TaskEventInterface;
use exface\Core\Interfaces\Events\ResultEventInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Event fired after an action is performed but before the transaction is autocommitted.
 * 
 * @event exface.Core.Action.OnActionPerformed
 *
 * @author Andrej Kabachnik
 *        
 */
class OnActionPerformedEvent extends AbstractActionEvent implements TaskEventInterface, ResultEventInterface
{
    private $result = null;
    
    private $transaction = null;
    
    private $inputDataCallback = null;
    
    /**
     * 
     * @param ActionInterface $action
     * @param ResultInterface $result
     * @param DataTransactionInterface $transaction
     */
    public function __construct(ActionInterface $action, ResultInterface $result, DataTransactionInterface $transaction, callable $inputDataResolver)
    {
        parent::__construct($action);
        $this->result = $result;
        $this->transaction = $transaction;
        $this->inputDataCallback = $inputDataResolver;
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
    
    /**
     * Returns a data sheet with the fully resolved input data incl. all mappers, checks, etc.
     * 
     * @return DataSheetInterface
     */
    public function getActionInputData() : DataSheetInterface
    {
        $callback = $this->inputDataCallback;
        return $callback();
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Action.OnActionPerformed';
    }
}