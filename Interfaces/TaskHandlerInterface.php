<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;

interface TaskHandlerInterface
{
    /**
     * 
     * @param TaskInterface $taks
     * @return TaskResultInterface
     */
    public function handle(TaskInterface $taks) : TaskResultInterface;
}