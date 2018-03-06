<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iNavigate;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Factories\TaskResultFactory;

class GoBack extends AbstractAction implements iNavigate
{

    protected function init()
    {
        $this->setIcon(Icons::ARROW_LEFT);
    }

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {
        return TaskResultFactory::createEmptyResult($task);
    }
}
?>