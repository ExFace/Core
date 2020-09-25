<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

interface TaskQueueInterface
{
    /**
     * 
     * @param TaskInterface $task
     * @return ResultInterface
     */
    public function handle(TaskInterface $task, string $producer, $sync = false) : ResultInterface;
}