<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
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

    final protected function performQuery(DataQueryInterface $query)
    {
        if (is_null($this->getCurrentConnection())) {
            $this->connect();
        }
        $query->setConnection($this);
        return $this->performQuerySql($query);
    }

    abstract protected function performQuerySql(SqlDataQuery $query);

    public function getCurrentConnection()
    {
        return $this->current_connection;
    }

    protected function setCurrentConnection($value)
    {
        $this->current_connection = $value;
        return $this;
    }

    public function transactionIsStarted()
    {
        return $this->transaction_started;
    }

    protected function setTransactionStarted($value)
    {
        $this->transaction_started = BooleanDataType::cast($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::runSql()
     */
    public function runSql($string)
    {
        $query = new SqlDataQuery();
        $query->setSql($string);
        return $this->query($query);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\TextualQueryConnectorInterface::runCustomQuery()
     */
    public function runCustomQuery(string $string) : DataQueryInterface
    {
        return $this->runSql($string);
    }

    public function getUser()
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
    public function setUser($value)
    {
        $this->user = $value;
        return $this;
    }

    public function getPassword()
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
    public function setPassword($value)
    {
        $this->password = $value;
        return $this;
    }

    public function getHost()
    {
        return $this->host;
    }

    /**
     * Host name or IP address to be used in this connection
     *
     * @uxon-property host
     * @uxon-type string
     *
     * @param string $value            
     * @return AbstractSqlConnector
     */
    public function setHost($value)
    {
        $this->host = $value;
        return $this;
    }

    public function getPort()
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
     * @param integer $value            
     * @return AbstractSqlConnector
     */
    public function setPort($value)
    {
        $this->port = $value;
        return $this;
    }

    public function getCharacterSet()
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
    public function setCharacterSet($value)
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
     * Refet to the documentation of the specific model builder for details!
     * 
     * @uxon-property relation_matcher
     * @uxon-type string
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
}