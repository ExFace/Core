<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\CommonLogic\AbstractActionDeferred;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use exface\Core\Interfaces\Actions\iModifyData;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class GeneratePWA extends AbstractActionDeferred implements iModifyData
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon('clock-o');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performImmediately()
     */
    protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
    {
        return [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    protected function performDeferred() : \Generator
    {
        
    }
}