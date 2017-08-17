<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionTransactionStartError;
use exface\Core\Exceptions\DataSources\DataConnectionCommitFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionRollbackFailedError;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\CommonLogic\Modelizers\MySqlModelizer;

/**
 * Data source connector for MySQL databases
 *
 * @author Andrej Kabachnik
 */
class MySqlConnector extends AbstractSqlConnector
{

    private $dbase = null;

    private $connection_method = null;

    private $use_persistant_connection = false;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        $safe_count = 0;
        $conn = null;
        
        $this->enableErrorExceptions();
        
        while (! $conn && $safe_count < 3) {
            try {
                if ($this->getUsePersistantConnection()) {
                    $conn = mysqli_pconnect($this->getHost(), $this->getUser(), $this->getPassword(), $this->getDbase());
                } else {
                    $conn = mysqli_connect($this->getHost(), $this->getUser(), $this->getPassword(), $this->getDbase());
                }
            } catch (\mysqli_sql_exception $e) {
                // Do nothing, try again later
            }
            if (! $conn) {
                sleep(1);
                $safe_count ++;
            }
        }
        if (! $conn) {
            throw new DataConnectionFailedError($this, 'Failed to create the database connection for "' . $this->getAliasWithNamespace() . '"!', '6T2TBVR', $e);
        } else {
            // Apply autocommit option
            if ($this->getAutocommit()) {
                mysqli_autocommit($conn, true);
            } else {
                mysqli_autocommit($conn, false);
            }
            
            // Set the character set
            // mysqli_query($conn, "{$this->getConnectionMethod()} {$this->getCharset()}");
            if (function_exists('mysqli_set_charset')) {
                mysqli_set_charset($conn, $this->getCharset());
            } else {
                mysqli_query($conn, "SET NAMES {$this->getCharset()}");
            }
            
            $this->setCurrentConnection($conn);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performDisconnect()
     */
    protected function performDisconnect()
    {
        try {
            mysqli_close($this->getCurrentConnection());
        } catch (\Throwable $e) {
            // ignore errors on close
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     * @param SqlDataQuery $query            
     */
    protected function performQuerySql(SqlDataQuery $query)
    {
        try {
            $result = mysqli_query($this->getCurrentConnection(), $query->getSql());
            $query->setResultResource($result);
        } catch (\mysqli_sql_exception $e) {
            throw new DataQueryFailedError($query, "SQL query failed! " . $e->getMessage(), '6T2T2UI', $e);
        }
        return $query;
    }

    protected function getLastError()
    {
        return mysqli_error($this->getCurrentConnection()) . ' (Error ' . mysqli_errno($this->getCurrentConnection() . ')');
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::makeArray()
     */
    public function makeArray(SqlDataQuery $query)
    {
        $rs = $query->getResultResource();
        if (! ($rs instanceof \mysqli_result))
            return array();
        $array = array();
        while ($row = mysqli_fetch_assoc($rs)) {
            $array[] = $row;
        }
        return $array;
    }

    public function getInsertId(SqlDataQuery $query)
    {
        try {
            return mysqli_insert_id($this->getCurrentConnection());
        } catch (\mysqli_sql_exception $e) {
            throw new DataQueryFailedError($query, "Cannot get insert_id for SQL query: " . $e->getMessage(), '6T2TCAJ', $e);
        }
    }

    public function getAffectedRowsCount(SqlDataQuery $query)
    {
        try {
            return mysqli_affected_rows($this->getCurrentConnection());
        } catch (\mysqli_sql_exception $e) {
            throw new DataQueryFailedError($query, "Cannot count affected rows in SQL query: " . $e->getMessage(), '6T2TCL6', $e);
        }
    }

    public function transactionStart()
    {
        if (! $this->transactionIsStarted()) {
            // Make sure, the connection is established
            if (! $this->isConnected()) {
                $this->connect();
            }
            
            try {
                mysqli_begin_transaction($this->getCurrentConnection());
                $this->setTransactionStarted(true);
            } catch (\mysqli_sql_exception $e) {
                throw new DataConnectionTransactionStartError($this, "Cannot start transaction: " . $e->getMessage(), '6T2T2JM', $e);
            }
        }
        return $this;
    }

    public function transactionCommit()
    {
        // Do nothing if the autocommit option is set for this connection
        if ($this->getAutocommit()) {
            return $this;
        }
        
        try {
            return mysqli_commit($this->getCurrentConnection());
        } catch (\mysqli_sql_exception $e) {
            throw new DataConnectionCommitFailedError($this, "Commit failed: " . $e->getMessage(), '6T2T2O9', $e);
        }
        return $this;
    }

    public function transactionRollback()
    {
        // Throw error if trying to rollback a transaction with autocommit enabled
        if ($this->getAutocommit()) {
            throw new DataConnectionRollbackFailedError($this, 'Cannot rollback transaction in "' . $this->getAliasWithNamespace() . '": The autocommit options is set to TRUE for this connection!');
        }
        
        try {
            return mysqli_rollback($this->getCurrentConnection());
        } catch (\mysqli_sql_exception $e) {
            throw new DataConnectionRollbackFailedError($this, "Rollback failed: " . $e->getMessage(), '6T2T2S1', $e);
        }
        return $this;
    }

    public function freeResult(SqlDataQuery $query)
    {
        mysqli_free_result($query->getResultResource());
    }

    public function getDbase()
    {
        return $this->dbase;
    }

    /**
     * Sets the database name to be used in this connection
     *
     * @uxon-property dbase
     * @uxon-type string
     *
     * @param string $value            
     * @return MySqlConnector
     */
    public function setDbase($value)
    {
        $this->dbase = $value;
        return $this;
    }

    public function getConnectionMethod()
    {
        return $this->connection_method;
    }

    /**
     * Sets the connection method to be used in this connection
     *
     * @uxon-property connection_method
     * @uxon-type string
     *
     * @param string $value            
     * @return MySqlConnector
     */
    public function setConnectionMethod($value)
    {
        $this->connection_method = $value;
        return $this;
    }

    public function getCharset()
    {
        return $this->getCharacterSet();
    }

    /**
     * Sets the character set to be used in this connection (same as "character_set")
     *
     * @uxon-property charset
     * @uxon-type string
     *
     * @see set_character_set()
     * @param string $value            
     * @return MySqlConnector
     */
    public function setCharset($value)
    {
        return $this->setCharacterSet($value);
    }

    public function getUsePersistantConnection()
    {
        return $this->use_persistant_connection;
    }

    /**
     * Set to TRUE to use persistant connections.
     *
     * @uxon-property use_persistant_connection
     * @uxon-type boolean
     *
     * @see set_character_set()
     * @param boolean $value            
     * @return MySqlConnector
     */
    public function setUsePersistantConnection($value)
    {
        $this->use_persistant_connection = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('dbase', $this->getDbase());
        $uxon->setProperty('use_persistant_connection', $this->getUsePersistantConnection());
        return $uxon;
    }

    protected function enableErrorExceptions()
    {
        // Make mysqli produce exceptions instead of errors
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::setCurrentConnection()
     */
    protected function setCurrentConnection($mysqli_connection_instance)
    {
        if (! ($mysqli_connection_instance instanceof \mysqli)) {
            throw new DataConnectionFailedError($this, 'Connection to MySQL failed: instance of \mysqli expected, "' . gettype($mysqli_connection_instance) . '" given instead!');
        }
        parent::setCurrentConnection($mysqli_connection_instance);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getModelizer()
     */
    public function getModelizer()
    {
        return new MySqlModelizer($this);
    }
}
?>