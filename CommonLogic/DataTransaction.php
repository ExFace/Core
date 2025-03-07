<?php
namespace exface\Core\CommonLogic;

use exface\Core\Events\Transaction\OnBeforeTransactionCommitEvent;
use exface\Core\Events\Transaction\OnBeforeTransactionRollbackEvent;
use exface\Core\Events\Transaction\OnTransactionStartEvent;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\DataSources\DataManagerInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Exceptions\DataSources\DataTransactionCommitError;
use exface\Core\Exceptions\DataSources\DataTransactionRollbackError;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSources\DataTransactionStartError;

class DataTransaction implements DataTransactionInterface
{

    private $data_manager = null;

    private $connections = array();

    private $is_started = false;

    private $is_committed = false;

    private $is_rolled_back = false;

    public function __construct(DataManagerInterface $manager)
    {
        $this->data_manager = $manager;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::getDataManager()
     */
    public function getDataManager() : DataManagerInterface
    {
        return $this->data_manager;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::start()
     */
    public function start() : DataTransactionInterface
    {
        // Do nothing if the transaction was started already
        if ($this->is_started === true) {
            return $this;
        }
        $this->is_started = true;
        $this->getWorkbench()->eventManager()->dispatch(new OnTransactionStartEvent($this));
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::commit()
     */
    public function commit() : DataTransactionInterface
    {
        // Ignore commmit if the transaction did not affect any connections or has not even been started
        if (! $this->isStarted() || $this->isEmpty()) {
            return $this;
        }
        // If it is already rolled back, we cannot commit!
        if ($this->isRolledBack()) {
            throw new DataTransactionCommitError('Cannot commit a transaction, that has already been rolled back!', '6T5VIIA');
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeTransactionCommitEvent($this));
        
        foreach ($this->getDataConnections() as $connection) {
            try {
                $connection->transactionCommit();
                $this->is_committed = true;
            } catch (ErrorExceptionInterface $e) {
                $this->rollback();
            }
        }
        
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::rollback()
     */
    public function rollback() : DataTransactionInterface
    {
        // Ignore rollback if the transaction did not affect any connections or has not even been started
        if (! $this->isStarted() || $this->isEmpty()) {
            return $this;
        }
        if ($this->isCommitted()) {
            throw new DataTransactionRollbackError('Cannot roll back a transaction, that has already been committed!', '6T5VIT8');
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeTransactionRollbackEvent($this));
        
        foreach ($this->getDataConnections() as $connection) {
            try {
                $connection->transactionRollback();
                $this->is_rolled_back = true;
            } catch (ErrorExceptionInterface $e) {
                throw new DataTransactionRollbackError('Cannot rollback transaction for "' . $connection->getAliasWithNamespace() . '": ' . $e->getMessage(), null, $e);
            }
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::isStarted()
     */
    public function isStarted() : bool
    {
        return $this->is_started;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::isRolledBack()
     */
    public function isRolledBack() : bool
    {
        return $this->is_rolled_back;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::isCommitted()
     */
    public function isCommitted() : bool
    {
        return $this->is_committed;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::isOpen()
     */
    public function isOpen() : bool
    {
        return $this->isStarted() && ! $this->isCommitted() && ! $this->isRolledBack() && ! empty($this->getDataConnections());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::addDataConnection()
     */
    public function addDataConnection(DataConnectionInterface $connection) : DataTransactionInterface
    {
        if (! $this->isStarted()) {
            $this->start();
        }
        
        // See if the connection is already registered in this transaction
        $existing_connection = null;
        foreach ($this->getDataConnections() as $c) {
            if ($c === $connection) {
                $existing_connection = $c;
                break;
            } 
        }
        
        // If this is a new connection, start a transaction there and add it to this DataTransaction.
        // Otherwise make sure, there is a transaction started in the existing connection.
        if (! $existing_connection) {
            if (! $connection->transactionIsStarted()) {
                try {
                    $connection->transactionStart();
                } catch (ErrorExceptionInterface $e) {
                    throw new DataTransactionStartError('Cannot start new transaction for "' . $connection->getAliasWithNamespace() . '":' . $e->getMessage(), null, $e);
                }
            }
            $this->connections[] = $connection;
        } elseif (! $existing_connection->transactionIsStarted()) {
            try {
                $existing_connection->transactionStart();
            } catch (ErrorExceptionInterface $e) {
                throw new DataTransactionStartError('Cannot start new transaction for "' . $connection->getAliasWithNamespace() . '":' . $e->getMessage(), null, $e);
            }
        }
        
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::getDataConnections()
     */
    public function getDataConnections() : array
    {
        return $this->connections;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getDataManager()->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::isEmpty()
     */
    public function isEmpty() : bool
    {
        return empty($this->connections);
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::getId()
     */
    public function getId() : string
    {
        return spl_object_id($this);
    }
}