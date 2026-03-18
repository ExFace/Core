<?php

namespace exface\Core\Exceptions\Actions;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

class ActionTaskInvalidException extends ActionInputError
{
    private TaskInterface $task;
    
    public function __construct(ActionInterface $action, TaskInterface $task, $message, $alias = null, $previous = null)
    {
        $this->task = $task;
        parent::__construct($action, $message, $alias, $previous);
    }

    public function getTask() : TaskInterface
    {
        return $this->task;
    }
}