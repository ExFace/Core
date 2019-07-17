<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;

/**
 * Clears the entire cache of the workbench.
 * 
 * This action does not support any parameters or input data.
 * 
 * @author Andrej Kabachnik
 *
 */
class ClearCache extends AbstractAction
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $this->getWorkbench()->getCache()->clear();
        return ResultFactory::createMessageResult($task, 'Cache cleard');
    }
}