<?php
namespace exface\Core\Events\Transaction;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\DataTransactionEventInterface;

/**
 * Event fired after a new data transaction was started.
 *
 * @event exface.Core.Transaction.OnTransactionStart
 * 
 * @author  Andrej Kabachnik
 */
class OnTransactionStartEvent extends AbstractEvent implements DataTransactionEventInterface
{
    private $transaction;
    
    /**
     * 
     * @param mixed $transaction
     */
    public function __construct($transaction)
    {
        $this->transaction = $transaction;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\DataTransactionEventInterface::getTransaction()
     */
    public function getTransaction() : DataTransactionInterface
    {
        return $this->transaction;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench() : \exface\Core\Interfaces\WorkbenchInterface
    {
        return $this->transaction->getWorkbench();
    }
}