<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Tasks\ResultInterface;

interface ResultEventInterface extends EventInterface
{
    /**
     * Returns the task, for which the event was triggered.
     * 
     * @return ResultInterface
     */
    public function getResult() : ResultInterface;
}