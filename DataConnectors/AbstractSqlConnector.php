<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\UsernamePasswordAuthToken;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractSqlConnector extends AbstractDataConnector implements SqlDataConnectorInterface
{

    private $current_connection;

    private $connected;

    private $autocommit = false;

    private $transaction_started = false;

    private $user = null;

    private $password = null;

    private $host = null;

    private $port = null;

    private $character_set = null;
    
    private $relationMatcher = null;

    /**
     *
     * @return boolean
     */
    public function getAutocommit()
    {
        return $this->autocommit;
    }

    /**
     * Set to TRUE to perform a commit after every statement.
     * 
     * @uxon-property autocommit
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param boolean $value            
     */
    public function setAutocommit($value)
    {
        $this->autocommit = BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     */
    final protected function performQuery(DataQueryInterface $query)
    {
        if (! $this->isConnected()) {
            $this->connect();
        }
        $query->setConnection($this);       
        if (! ($query instanceof SqlDataQuery)) {
            throw new DataQueryFailedError($query, 'Invalid query type for connection ' . $this->getAliasWithNamespace() . ': only SQL queries supported!');
        }
        // If it is a multi-statement query and this connector supports batch delimiters, 
        // ensure that the query has the correct delimiter. Splitting the query into batches
        // is done by the connector itself. This step here is just normalization.
        if ($query->isMultipleStatements() && $query->getBatchDelimiterPattern() === null && null !== $batchDelim = $this->getBatchDelimiterPattern()) {
            $query->setBatchDelimiterPattern($batchDelim);
        }
        return $this->performQuerySql($query);
    }

    /**
     * 
     * @param SqlDataQuery $query
     */
    abstract protected function performQuerySql(SqlDataQuery $query);

    /**
     * 
     * @return resource
     */
    public function getCurrentConnection()
    {
        return $this->current_connection;
    }

    /**
     * 
     * @param resource $value
     * @return \exface\Core\DataConnectors\AbstractSqlConnector
     */
    protected function setCurrentConnection($value) : AbstractSqlConnector
    {
        $this->current_connection = $value;
        return $this;
    }
    
    /**
     * 
     * @return AbstractSqlConnector
     */
    protected function resetCurrentConnection() : AbstractSqlConnector
    {
        $this->current_connection = null;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::transactionIsStarted()
     */
    public function transactionIsStarted()
    {
        return $this->transaction_started;
    }

    /**
     * 
     * @param bool $value
     * @return AbstractSqlConnector
     */
    protected function setTransactionStarted(bool $value) : AbstractSqlConnector
    {
        $this->transaction_started = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::runSql()
     */
    public function runSql($string, bool $multiquery = null)
    {
        $query = new SqlDataQuery();
        $query->setSql($string);
        if ($multiquery !== null) {
            $query->forceMultipleStatements($multiquery);
        }
        return $this->query($query);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\TextualQueryConnectorInterface::runCustomQuery()
     */
    public function runCustomQuery(string $string) : DataQueryInterface
    {
        return $this->runSql($string, true);
    }

    /**
     * 
     * @return string|NULL
     */
    protected function getUser() : ?string
    {
        return $this->user;
    }

    /**
     * The user name to be used in this connection
     *
     * @uxon-property user
     * @uxon-type string
     *
     * @param string $value            
     * @return AbstractSqlConnector
     */
    protected function setUser(string $value) : AbstractSqlConnector
    {
        $this->user = $value;
        return $this;
    }

    /**
     * 
     * @return string|NULL
     */
    protected function getPassword() : ?string
    {
        return $this->password;
    }

    /**
     * Password to be used in this connection
     *
     * @uxon-property password
     * @uxon-type password
     *
     * @param string $value            
     * @return AbstractSqlConnector
     */
    protected function setPassword(string $value) : AbstractSqlConnector
    {
        $this->password = $value;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getHost() : string
    {
        return $this->host;
    }

    /**
     * Host name or IP address to be used in this connection
     *
     * @uxon-property host
     * @uxon-type string
     * @uxon-required true
     *
     * @param string $value            
     * @return AbstractSqlConnector
     */
    public function setHost(string $value) : AbstractSqlConnector
    {
        $this->host = $value;
        return $this;
    }

    /**
     * 
     * @return int|NULL
     */
    protected function getPort() : ?int
    {
        return $this->port;
    }

    /**
     * The port number to be used in this connection
     * 
     * If not set, the default port of the database is used automatically.
     *
     * @uxon-property port
     * @uxon-type number
     *
     * @param int $value            
     * @return AbstractSqlConnector
     */
    public function setPort(int $value) : AbstractSqlConnector
    {
        $this->port = $value;
        return $this;
    }

    /**
     * 
     * @return string|NULL
     */
    protected function getCharacterSet() : ?string
    {
        return $this->character_set;
    }

    /**
     * Character set to be used in this connection.
     * 
     * Possible values depend on the database used - refer to it's documentation for more information.
     *
     * @uxon-property character_set
     * @uxon-type string
     * @uxon-default utf8
     *
     * @param string $value            
     * @return AbstractSqlConnector
     */
    public function setCharacterSet(string $value) : AbstractSqlConnector
    {
        $this->character_set = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('user', $this->getUser());
        $uxon->setProperty('password', $this->getPassword());
        $uxon->setProperty('host', $this->getHost());
        $uxon->setProperty('port', $this->getPort());
        $uxon->setProperty('autocommit', $this->getAutocommit());
        return $uxon;
    }
    
    /**
     *
     * @return string
     */
    public function getRelationMatcher() : ?string
    {
        return $this->relationMatcher;
    }
    
    /**
     * Regular expression for the model builder to find relations (foreign keys) automatically.
     * 
     * Refer to the documentation of the specific model builder for details!
     * 
     * Typical examples:
     * - `/(?<alias>(?<table>.*))_(?<key>id)/i` - product_id -> relation to table "product"
     * - `/(?<alias>(?<table>.*))(?<key>Id)/i` - ProductId -> relation to table "product"
     * 
     * @uxon-property relation_matcher
     * @uxon-type string
     * @uxon-template /(?<alias>(?<table>.*))_(?<key>id)/i
     * 
     * @param string $value
     * @return AbstractSqlConnector
     */
    public function setRelationMatcher(string $value) : AbstractSqlConnector
    {
        $this->relationMatcher = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token, bool $updateUserCredentials = true, UserInterface $credentialsOwner = null, bool $credentialsArePrivate = null) : AuthenticationTokenInterface
    {
        if (! $token instanceof UsernamePasswordAuthToken) {
            throw new InvalidArgumentException('Invalid token class "' . get_class($token) . '" for authentication via data connection "' . $this->getAliasWithNamespace() . '" - only "UsernamePasswordAuthToken" and derivatives supported!');
        }
            
        try {
            $prevConnection = $this->getCurrentConnection();
            $prevUsername = $this->getUser();
            $prevPassword = $this->getPassword();
            
            $this->setUser($token->getUsername());
            $this->setPassword($token->getPassword());
            $this->performConnect();
            
            $this->setUser($prevUsername);
            $this->setPassword($prevPassword);
            if ($prevConnection !== null) {
                $this->setCurrentConnection($prevConnection);
            }
        } catch (DataConnectionFailedError $e) {
            throw new AuthenticationFailedError($this, 'Authentication failed! ' . $e->getMessage(), null, $e);
        }
        
        if ($updateUserCredentials === true) {
            $user = $credentialsOwner;
            $uxon = new UxonObject([
                'user' => $token->getUsername(),
                'password' => $token->getPassword()
            ]);
            $credentialSetName = ($token->getUsername() ? $token->getUsername() : 'no username') . ' - ' . $this->getName();
            $this->saveCredentials($uxon, $credentialSetName, $user, $credentialsArePrivate);
        }
        
        return $token;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container, bool $saveCredentials = true, UserSelectorInterface $credentialsOwner = null) : iContainOtherWidgets
    {
        $loginForm = $this->createLoginForm($container, $saveCredentials, $credentialsOwner);
        
        // Add USERNAME and PASSWORD on top of the default fields of the form.
        $loginForm->addWidget(WidgetFactory::createFromUxonInParent($loginForm, new UxonObject([
                'attribute_alias' => 'USERNAME',
                'required' => true
        ])), 0);
        $loginForm->addWidget(WidgetFactory::createFromUxonInParent($loginForm, new UxonObject([
            'attribute_alias' => 'PASSWORD'
        ])), 1);
        
        $container->addWidget($loginForm);
        return $container;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::escapeString()
     */
    public function escapeString(string $string) : string
    {
        if (function_exists('mb_ereg_replace')) {
            return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x27\x5C]', '\\\0', $string);
        } else {
            return preg_replace('~[\x00\x0A\x0D\x1A\x22\x27\x5C]~u', '\\\$0', $string);
        }
    }
    
    /**
     * Returns the batch delimiter regex pattern or NULL if batches are not supported by this connector.
     * 
     * Batch delimiters are used in some SQL engines like Microsoft SQL Server to write large SQL
     * sripts to be split into batches at runtime. In MS SQL the batch delimiter is the `GO` keyword.
     * 
     * @return string|NULL
     */
    protected function getBatchDelimiterPattern() : ?string
    {
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::canJoin()
     */
    public function canJoin(DataConnectionInterface $otherConnection) : bool
    {
        if (! $otherConnection instanceof SqlDataConnectorInterface) {
            return false;
        }
        if (! $otherConnection instanceof $this) {
            return false;
        }
        if ($this->getHost() !== $otherConnection->getHost()) {
            return false;
        }
        return true;
    }
}