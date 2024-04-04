<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\Factories\DataSourceFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class DataManager implements DataManagerInterface
{
    private $active_sources = [];

    private $exface;
    
    private $transactions = [];

    /**
     *
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    function __construct(WorkbenchInterface $exface)
    {
        $this->exface = $exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::getDataSource()
     */
    function getDataSource($uid, $data_connection_id_or_alias = NULL)
    {
        // first check the cache
        if ($this->active_sources[$uid . '-' . $data_connection_id_or_alias]) {
            return $this->active_sources[$uid . '-' . $data_connection_id_or_alias];
        }
        
        // if it is a new source, create it here
        $data_source = DataSourceFactory::createFromModel($this->getWorkbench(), $uid, $data_connection_id_or_alias);
        $this->active_sources[$uid . '-' . $data_connection_id_or_alias] = $data_source;
        return $data_source;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::disconnectAll()
     */
    public function disconnectAll()
    {
        foreach ($this->active_sources as $src) {
            $src->getConnection()->disconnect();
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::startTransaction()
     */
    public function startTransaction() : DataTransactionInterface
    {
        $transaction = new DataTransaction($this);
        $transaction->start();
        $this->transactions[] = $transaction;
        return $transaction;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataManagerInterface::getTransactions()
     */
    public function getTransactions() : array
    {
        return $this->transactions;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    function getWorkbench()
    {
        return $this->exface;
    }
}