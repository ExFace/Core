<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionCommitFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionRollbackFailedError;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\Interfaces\Model\MetaModelPrototypeInterface;

interface DataConnectionInterface extends WorkbenchDependantInterface, AliasInterface, iCanBeConvertedToUxon, MetaModelPrototypeInterface
{
    /**
     * 
     * @return bool
     */
    public function hasModel() : bool;
    
    /**
     * 
     * @return string|NULL
     */
    public function getId() : ?string;
    
    /**
     * 
     * @param string $uid
     * @return DataConnectionInterface
     */
    public function setId(string $uid) : DataConnectionInterface;
    
    /**
     * 
     * @param string $alias
     * @param string $namespace
     * @return DataConnectionInterface
     */
    public function setAlias(string $alias, string $namespace = null) : DataConnectionInterface;
    
    /**
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     * 
     * @param string $string
     * @return DataConnectionInterface
     */
    public function setName(string $string) : DataConnectionInterface;

    /**
     * Connects to the data source using the configuration array passed to the constructor of the connector
     *
     * @triggers \exface\Core\Events\DataConnection\OnBeforeConnectEvent
     * @triggers \exface\Core\Events\DataConnection\OnConnectEvent
     * 
     * @return void
     */
    public function connect();
    
    /**
     * 
     * @return bool
     */
    public function isConnected() : bool;

    /**
     * Closes the connection to the data source
     *
     * @triggers \exface\Core\Events\DataConnection\OnBeforeDisconnectEvent
     * @triggers \exface\Core\Events\DataConnection\OnDisconnectEvent
     * 
     * @return void
     */
    public function disconnect();

    /**
     * Queries the data source using the passed query object (presumably build by a suitable query builder) and returns
     * a query object containing the result in addition to the query.
     * The form in which the result is stored depends
     * on the specific implementation - it must be readable by compatible query builders but apart from that it can be
     * anything.
     *
     * @param DataQueryInterface $query
     * 
     * @triggers \exface\Core\Events\DataConnection\OnBeforeQueryEvent
     * @triggers \exface\Core\Events\DataConnection\OnQueryEvent         
     * 
     * @return DataQueryInterface
     */
    public function query(DataQueryInterface $query) : DataQueryInterface;

    /**
     * Starts a new transaction in the data source.
     *
     * @throws DataConnectionFailedError if no transaction could be started
     * @return DataConnectionInterface
     */
    public function transactionStart();

    /**
     * Commits the current transaction in the data source.
     * Returns TRUE on success and FALSE otherwise.
     *
     * @throws DataConnectionCommitFailedError if the transaction cannot be committed
     * @return DataConnectionInterface
     */
    public function transactionCommit();

    /**
     * Rolls back the current transaction in the data source.
     *
     * @throws DataConnectionRollbackFailedError if the transaction cannot be rolled back
     * @return DataConnectionInterface
     */
    public function transactionRollback();

    /**
     * Returns true if a transaction is currently open
     *
     * @return boolean
     */
    public function transactionIsStarted();

    /**
     * Returns an instance of the model builder, that can be used to generate meta models from this data connection.
     *
     * @return ModelBuilderInterface
     */
    public function getModelBuilder();
    
    /**
     * 
     * @return DataConnectionSelectorInterface|NULL
     */
    public function getSelector() : ?DataConnectionSelectorInterface;
    
    /**
     * 
     * @return DataConnectorSelectorInterface
     */
    public function getPrototypeSelector() : DataConnectorSelectorInterface;   
    
    public function isReadOnly() : bool;
    
    public function setReadOnly(bool $trueOrFalse) : DataConnectionInterface;
}