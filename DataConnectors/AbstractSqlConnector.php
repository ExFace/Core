<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\DataTypes\BooleanDataType;

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
     *
     * @param boolean $value            
     */
    public function setAutocommit($value)
    {
        $this->autocommit = \exface\Core\DataTypes\BooleanDataType::cast($value);
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
        $this->setConnected(true);
        return $this;
    }

    public function isConnected()
    {
        return $this->connected;
    }

    protected function setConnected($value)
    {
        $this->connected = BooleanDataType::cast($value);
        return $this;
    }

    public function transactionIsStarted()
    {
        return $this->transaction_started;
    }

    protected function setTransactionStarted($value)
    {
        $this->transaction_started = \exface\Core\DataTypes\BooleanDataType::cast($value);
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

    public function getUser()
    {
        return $this->user;
    }

    /**
     * Sets the user name to be used in this connection
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
     * Sets the password to be used in this connection
     *
     * @uxon-property password
     * @uxon-type string
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
     * Sets the host name or IP address to be used in this connection
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
     * Sets the port to be used in this connection
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
     * Sets the character set to be used in this connection
     *
     * @uxon-property character_set
     * @uxon-type string
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
     * 
     * @param string $value
     * @return AbstractSqlConnector
     */
    public function setRelationMatcher(string $value) : AbstractSqlConnector
    {
        $this->relationMatcher = $value;
        return $this;
    }
    
}
?>