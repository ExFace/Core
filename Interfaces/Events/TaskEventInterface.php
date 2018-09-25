<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Tasks\TaskInterface;

interface TaskEventInterface extends EventInterface
{
    /**
     * Returns the task, for which the event was triggered.
     * 
     * @return TaskInterface
     */
    public function getTask() : TaskInterface;
}