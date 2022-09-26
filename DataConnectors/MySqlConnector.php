<?php
namespace exface\Core\DataConnectors;

use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionTransactionStartError;
use exface\Core\Exceptions\DataSources\DataConnectionCommitFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionRollbackFailedError;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\ModelBuilders\MySqlModelBuilder;
use exface\Core\Interfaces\Exceptions\DataQueryExceptionInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataQueryConstraintError;

/**
 * Data source connector for MySQL databases
 *
 * @author Andrej Kabachnik
 */
class MySqlConnector extends AbstractSqlConnector
{

    private $dbase = null;

    private $connection_method = 'SET CHARACTER SET';

    private $use_persistant_connection = false;
    
    private $affectedRows = null;
    
    private $socket = null;

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
        $e = null;
        while (! $conn && $safe_count < 3) {
            try {
                $host = ($this->getUsePersistantConnection() ? 'p:' : '') . $this->getHost();
                $conn = mysqli_connect($host, $this->getUser(), $this->getPassword(), $this->getDbase(), $this->getPort(), $this->getSocket());
            } catch (\mysqli_sql_exception $e) {
                // Do nothing, try again later
            }
            if (! $conn) {
                sleep(1);
                $safe_count ++;
            }
        }
        if (! $conn) {
            throw new DataConnectionFailedError($this, 'Failed to create the database connection for "' . $this->getAliasWithNamespace() . '"' . ($e ? ': ' . $e->getMessage() : '') . '!', '6T2TBVR', $e);
        } else {
            // Apply autocommit option
            if ($this->getAutocommit()) {
                mysqli_autocommit($conn, true);
            } else {
                mysqli_autocommit($conn, false);
            }
            
            // Set the character set
            if ($this->getCharacterSet()) {
                if (function_exists('mysqli_set_charset')) {
                    mysqli_set_charset($conn, $this->getCharset());
                } else {
                    mysqli_query($conn, "SET NAMES {$this->getCharset()}");
                }
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
            if ($conn = $this->getCurrentConnection()) {
                mysqli_close($conn);
            }
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
        $conn = $this->getCurrentConnection();
        $this->affectedRows = null;
        try {
            if ($query->isMultipleStatements()) {
                if (mysqli_multi_query($conn, $query->getSql())) {
                    $idx = 0;
                    do {
                        $idx++;
                        $this->affectedRows += mysqli_affected_rows($conn);
                        $result = mysqli_use_result($conn);
                        if (mysqli_more_results($conn)) {
                            //mysqli_free_result($result);
                        } else {
                            break;
                        }
                        if(!mysqli_next_result($conn) || mysqli_errno($conn)) {
                            throw $this->createQueryError($query, 'Error in query ' . $idx . ' of a multi-query statement. ' . $this->getLastError(), mysqli_errno($conn));
                        }
                    } while (true);
                }
            } else {
                $result = mysqli_query($conn, $query->getSql());
            }
            if ($result instanceof \mysqli_result) {
                $query->setResultResource($result);
            }
        } catch (\mysqli_sql_exception $e) {
            throw $this->createQueryError($query, $e->getMessage() . ' - SQL error code ' . $e->getCode(), null, $e);
        }
        return $query;
    }
    
    /**
     * 
     * @param DataQueryInterface $query
     * @param string $message
     * @param string $sqlErrorNo
     * @param \Exception $sqlException
     * @return DataQueryExceptionInterface
     */
    protected function createQueryError(DataQueryInterface $query, string $message, string $sqlErrorNo = null, \Exception $sqlException = null) : DataQueryExceptionInterface
    {
        $sqlErrorNo = $sqlErrorNo ?? $sqlException->getCode() ?? null;
        
        switch ($sqlErrorNo) {
            case 1062:
                return new DataQueryConstraintError($query, $message, '73II64M', $sqlException);
            default:
                return new DataQueryFailedError($query, $message, '6T2T2UI', $sqlException);
        }
    }

    protected function getLastError()
    {
        return mysqli_error($this->getCurrentConnection()) . ' (Error ' . mysqli_errno($this->getCurrentConnection()) . ')';
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
        if ($this->affectedRows !== null) {
            return $this->affectedRows;
        }
        
        try {
            $cnt = mysqli_affected_rows($this->getCurrentConnection());
            // mysqli_affected_rows() can return -1 in case of an error accoring to the docs. It seems,
            // though, that this happens sometimes in DELETE statements even if they were successfull.
            // Thus, we do a check for an error description to be sure and simply assume 0 rows affected
            // if there was no error.
            if ($cnt < 0) {
                if ($err = $this->getLastError()) {
                    throw new DataQueryFailedError($query, "Cannot count affected rows in SQL query: " . $err, '6T2TCL6');
                } else {
                    return null;
                }
            }
            return $cnt;
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
            mysqli_commit($this->getCurrentConnection());
            $this->setTransactionStarted(false);
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
            mysqli_rollback($this->getCurrentConnection());
            $this->setTransactionStarted(false);
        } catch (\Throwable $e) {
            throw new DataConnectionRollbackFailedError($this, "Rollback failed: " . $e->getMessage(), '6T2T2S1', $e);
        }
        return $this;
    }

    public function freeResult(SqlDataQuery $query)
    {
        if ($query->getResultResource() instanceof \mysqli_result) {
            mysqli_free_result($query->getResultResource());
        }
    }

    public function getDbase()
    {
        return $this->dbase;
    }

    /**
     * The database name to connect to (same as "database")
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

    /**
     * 
     * @return string
     */
    private function getConnectionMethod()
    {
        return $this->connection_method;
    }

    /**
     * @deprecated use setCharsetTranslation() instead.
     * @param string $value            
     * @return MySqlConnector
     */
    public function setConnectionMethod($value)
    {
        $this->connection_method = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    protected function getCharsetTranslation() : bool
    {
        return $this->connection_method === 'SET_NAMES';
    }
    
    /**
     * Forces the connection to translate character sets to the one specified in `charset`.
     * 
     * Technically this works by using `SET NAMES` instead of `SET CHARACTER SET` when
     * initializing the connection.
     * 
     * `SET NAMES` forces the connection charset to whatever you specify, which will translate 
     * characters between charsets, but that process is lossy. Instead, you should make sure 
     * your database container is set the same as your intended character set. `SET CHARACTER SET` 
     * actually uses the value of the database container to set the connection charset, 
     * regardless of what character set you specify for connection to use. If you ensure that the 
     * database container has the proper charset for the data your are storing in the actual 
     * tables, and that your `charset` setting in the connection either matches this, or another 
     * charset you want to translate the data to/from when talking to the DB (though the latter 
     * is not recommended), this should work flawlessly.
     * 
     * @uxon-property charset_translation
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return MySqlConnector
     */
    public function setCharsetTranslation(bool $value) : MySqlConnector
    {
        $this->connection_method = ($value === true ? 'SET NAMES' : 'SET CHARACTER SET');
        return $this;
    }
    

    public function getCharset() : ?string
    {
        return $this->getCharacterSet();
    }

    /**
     * The character set to be used in this connection (same as "character_set")
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
     * @uxon-default false
     *
     * @see set_character_set()
     * @param boolean $value            
     * @return MySqlConnector
     */
    public function setUsePersistantConnection($value)
    {
        $this->use_persistant_connection = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getSocket() : ?string
    {
        return $this->socket;
    }
    
    /**
     * Specifies the socket or named pipe that should be used.
     * 
     * @uxon-property socket
     * @uxon-type string
     * 
     * @param string $value
     * @return MySqlConnector
     */
    public function setSocket(string $value) : MySqlConnector
    {
        $this->socket = $value;
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
        if ($this->socket !== null) {
            $uxon->setProperty('socket', $this->socket);
        }
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
    protected function setCurrentConnection($mysqli_connection_instance) : AbstractSqlConnector
    {
        if (! ($mysqli_connection_instance instanceof \mysqli)) {
            throw new DataConnectionFailedError($this, 'Connection to MySQL failed: instance of \mysqli expected, "' . gettype($mysqli_connection_instance) . '" given instead!');
        }
        return parent::setCurrentConnection($mysqli_connection_instance);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getModelBuilder()
     */
    public function getModelBuilder()
    {
        return new MySqlModelBuilder($this);
    }
}
?>