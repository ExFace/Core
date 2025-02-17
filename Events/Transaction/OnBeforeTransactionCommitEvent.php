<?php
namespace exface\Core\Events\Transaction;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\DataTransactionEventInterface;

/**
 * Event fired before a data transaction is committed.
 *
 * @event exface.Core.Transaction.OnBeforeTransactionCommit
 * 
 * @author  Andrej Kabachnik
 */
class OnBeforeTransactionCommitEvent extends AbstractEvent implements DataTransactionEventInterface
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