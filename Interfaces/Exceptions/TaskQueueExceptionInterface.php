<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\TaskQueueInterface;

Interface TaskQueueExceptionInterface
{
    /**
     *
     * @return TaskQueueInterface
     */
    public function getQueue();
}