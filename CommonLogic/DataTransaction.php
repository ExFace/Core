<?php
namespace exface\Core\CommonLogic;

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
    public function getDataManager()
    {
        return $this->data_manager;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::start()
     */
    public function start()
    {
        $this->is_started = true;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::commit()
     */
    public function commit()
    {
        if ($this->isRolledBack()) {
            throw new DataTransactionCommitError('Cannot commit a transaction, that has already been rolled back!', '6T5VIIA');
        }
        
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
    public function rollback()
    {
        if ($this->isCommitted()) {
            throw new DataTransactionRollbackError('Cannot roll back a transaction, that has already been committed!', '6T5VIT8');
        }
        
        foreach ($this->getDataConnections() as $connection) {
            try {
                $connection->transactionRollback();
                $this->is_rolled_back = true;
            } catch (ErrorExceptionInterface $e) {
                throw new DataTransactionRollbackError('Cannot rollback transaction for "' . $connection->getAliasWithNamespace() . '":' . $e->getMessage());
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
    public function isStarted()
    {
        return $this->is_started;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::isRolledBack()
     */
    public function isRolledBack()
    {
        return $this->is_rolled_back;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::isCommitted()
     */
    public function isCommitted()
    {
        return $this->is_committed;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::addDataConnection()
     */
    public function addDataConnection(DataConnectionInterface $connection)
    {
        if (! $this->isStarted()) {
            $this->start();
        }
        
        // See if the connection is already registered in this transaction
        foreach ($this->getDataConnections() as $existing_connection) {
            if ($existing_connection == $connection) {} else {
                $existing_connection = null;
            }
        }
        
        // If this is a new connection, start a transaction there and add it to this DataTransaction.
        // Otherwise make sure, there is a transaction started in the existing connection.
        if (! $existing_connection) {
            try {
                $connection->transactionStart();
            } catch (ErrorExceptionInterface $e) {
                throw new DataTransactionStartError('Cannot start new transaction for "' . $connection->getAliasWithNamespace() . '":' . $e->getMessage());
            }
            $this->connections[] = $connection;
        } elseif (! $existing_connection->transactionIsStarted()) {
            try {
                $existing_connection->transactionStart();
            } catch (ErrorExceptionInterface $e) {
                throw new DataTransactionStartError('Cannot start new transaction for "' . $connection->getAliasWithNamespace() . '":' . $e->getMessage());
            }
        }
        
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataTransactionInterface::getDataConnections()
     */
    public function getDataConnections()
    {
        return $this->connections;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getDataManager()->getWorkbench();
    }
}