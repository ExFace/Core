<?php

namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

class Sleep extends AbstractAction
{

    /**
     * @inheritDoc
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        sleep(160);
        return ResultFactory::createEmptyResult($task);
    }
}