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
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Security\AuthenticationProviderInterface;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\UserInterface;

interface DataConnectionInterface extends WorkbenchDependantInterface, AliasInterface, iCanBeConvertedToUxon, MetaModelPrototypeInterface, AuthenticationProviderInterface
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
    
    /**
     * Returns the time zone to be expected for time values from this connection, that do not have an explizit time zone.
     * 
     * Returns NULL if the connection has the same time zone, as the workbench
     * 
     * @return string|NULL
     */
    public function getTimeZone() : ?string;
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::createLoginWidget()
     * 
     * @param iContainOtherWidgets $container
     * @param bool $saveCredentials
     * @param UserSelectorInterface
     * 
     * @return iContainOtherWidgets
     */
    public function createLoginWidget(iContainOtherWidgets $container, bool $saveCredentials = true, UserSelectorInterface $credentialsOwner = null) : iContainOtherWidgets;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token, bool $updateUserCredentials = true, UserInterface $credentialsOwner = null, bool $credentialsArePrivate = null) : AuthenticationTokenInterface;
    
    /**
     * 
     * @param DataConnectionSelectorInterface|string $selectorOrString
     * @return bool
     */
    public function isExactly($selectorOrString) : bool;
}