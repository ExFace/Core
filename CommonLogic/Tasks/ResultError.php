<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * Implementation for a result of a task that lead to an error.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultError extends ResultMessage
{
    private $responseCode = 400;
    
    private $exception = null;
    
    /**
     * 
     * @param TaskInterface $task
     * @param \Throwable $e
     */
    public function __construct(TaskInterface $task, \Throwable $e = null)
    {
        $this->task = $task;
        $this->exception = $e;
        $this->workbench = $task->getWorkbench();
    }
    
    /**
     * 
     * @return \Throwable
     */
    public function getException(): \Throwable
    {
        return $this->exception;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::getMessage()
     */
    public function getMessage(): string
    {
        return $this->getException()->getMessage();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::isEmpty()
     */
    public function isEmpty() : bool
    {
        return false;   
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::isUndoable()
     */
    public function isUndoable(): bool
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::isContextModified()
     */
    public function isContextModified(): bool
    {
        return $this->isContextModified;
    }
}