<?php
namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

interface TaskHandlerInterface
{
    /**
     * 
     * @param TaskInterface $taks
     * @return ResultInterface
     */
    public function handle(TaskInterface $taks) : ResultInterface;
}