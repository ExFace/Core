<?php
namespace exface\Core\Events\Action;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\ActionRuntimeEventInterface;

/**
 * Event fired before an action is performed.
 *
 * @event exface.Core.Action.OnBeforeActionPerformed
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforeActionPerformedEvent extends AbstractActionEvent implements ActionRuntimeEventInterface
{
    private $task = null;
    
    private $transaction = null;
    
    private $inputDataCallback = null;
    
    /**
     * 
     * @param ActionInterface $action
     * @param TaskInterface $task
     * @param DataTransactionInterface $transaction
     */
    public function __construct(ActionInterface $action, TaskInterface $task, DataTransactionInterface $transaction, callable $inputDataResolver)
    {
        parent::__construct($action);
        $this->task = $task;
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
     * @see \exface\Core\Interfaces\Events\ActionRuntimeEventInterface::getActionInputData()
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
        return 'exface.Core.Action.OnBeforeActionPerformed';
    }
}