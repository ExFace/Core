<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;

interface DataTransactionEventInterface extends EventInterface
{
    /**
     * Returns the transaction, for which the event was triggered.
     * 
     * @return DataTransactionInterface
     */
    public function getTransaction() : DataTransactionInterface;
}