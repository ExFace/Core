<?php
namespace exface\Core\DataConnectors;

use exface\Core\DataTypes\BinaryDataType;
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
 * ## Configuration
 * 
 * ```
 *  {
 *      "host": "localhost",
 *      "user": "root",
 *      "password": ""
 *  }
 * 
 * ```
 * 
 * ### Enabling SSL
 * 
 * For example, for Azure SQL for MySQL you will need to download a CA certificate `.pem`
 * file and put it at some place on your server. Assuming we put it into the `config`
 * folder of the workbench installation, the configuration would look like this:
 * 
 * ```
 *  {
 *      "host": "<service-name>.mysql.database.azure.com",
 *      "user": "<username>",
 *      "password": "<password>"
 *      "ssl_ca_certificate_path": "config/DigiCertGlobalRootG2.crt.pem"
 *  }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 */
class MySqlConnector extends AbstractSqlConnector
{
    const ERROR_CODE_GONE_AWAY = 2006;
    
    const ERRRO_CODE_CONTRAINT = 1062;

    private $dbase = null;

    private $connection_method = 'SET CHARACTER SET';

    private $use_persistant_connection = false;
    
    private $affectedRows = null;
    
    private $socket = null;
    
    private $multiqueryResults = null;
    
    private $reconnects = 0;

    // SSL settings - see https://www.php.net/manual/en/mysqli.ssl-set.php
    private $sslCACertificatePath = null;
    private $sslCertificatePath = null;
    private $sslCaPath = null;
    private $sslCipherAlgos = null;
    private $sslKey = null;

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
        $host = ($this->getUsePersistantConnection() ? 'p:' : '') . $this->getHost();
        $conn = mysqli_init();
        if ($this->isSslEnabled() === true) {
            mysqli_ssl_set($conn, $this->getSslKey(), $this->getSslCertificatePath(), $this->getSslCaCertificatePath(), null, null);
        }
        $connected = false;
        while (! $connected && $safe_count < 3) {
            try {
                $connected = mysqli_real_connect($conn, $host, $this->getUser(), $this->getPassword(), $this->getDbase(), $this->getPort(), $this->getSocket());
            } catch (\mysqli_sql_exception $e) {
                // Do nothing, try again later
            }
            if ($connected === false) {
                sleep(1);
                $safe_count ++;
            }
        }
        if ($connected === false || ! $conn) {
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
                $this->resetCurrentConnection();
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
                $this->multiqueryResults = [];
                if (mysqli_multi_query($conn, $query->getSql())) {
                    $idx = 0;
                    do {
                        $idx++;
                        $this->multiqueryResults[$idx] = [];
                        $this->affectedRows += mysqli_affected_rows($conn);
                        $result = mysqli_store_result($conn);
                        if ($result) {
                            $this->multiqueryResults[$idx] = mysqli_fetch_assoc($result);
                        } elseif (mysqli_errno($conn)) {
                            throw $this->createQueryError($query, 'Error in query ' . $idx . ' of a multi-query statement. ' . $this->getLastError(), mysqli_errno($conn));
                        }
                        if (mysqli_more_results($conn)) {
                            // Free the memory of the current result if it is not emtpy
                            // Note, an empty result here might come for a query that does not
                            // do anything and does not neccessarily indicate an error!
                            if ($result) {
                                mysqli_free_result($result);
                            }
                            
                            if(! mysqli_next_result($conn)) {
                                throw $this->createQueryError($query, 'Failed to get next SQL result in query ' . $idx . ' of a multi-query statement. ' . $this->getLastError(), mysqli_errno($conn));
                            }
                        } else {
                            break;
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
            if ($e->getCode() == self::ERROR_CODE_GONE_AWAY && $this->reconnects === 0) {
                $this->disconnect();
                $this->connect();
                $this->reconnects++;
                return $this->performQuerySql($query);
            }
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
            case self::ERRRO_CODE_CONTRAINT:
                // Constraint errors on binary keys will contain the key value in unreadable format like
                // "Duplicate entry '\x11\xEF\x91{WY\xA7`\x91{\x00PV\xBE\xF7]' for key ...".
                // Here we attempt to find the binary part and transform it to our standard hex
                // format. The constraint violating values are enclosed in single quotes `'` and separated
                // by dashes `-`, so we search for potential binary values: `'\x...'`, `'\x...-`, `-\x...-`
                // or `-\x...'`.
                $binaryMatches = [];
                $foundBinaries = preg_match_all("/['\-](\\\\x.*?)['\-]/", $message, $binaryMatches);
                if ($foundBinaries === 1) {
                    foreach ($binaryMatches[1] as $binaryString) {
                        // Decode the binary string
                        $raw_binary = preg_replace_callback('/\\\\x([0-9A-Fa-f]{2})/', function ($matches) {
                            return chr(hexdec($matches[1]));
                        }, $binaryString);
                        
                        // Convert the raw binary to a hex string (optional, for better readability)
                        $decodedHex = BinaryDataType::convertBinaryToHex($raw_binary);
                        $message = str_replace($binaryString, $decodedHex, $message);
                    }
                }
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
        $array = [];
        if ($query->isMultipleStatements() && ! empty($this->multiqueryResults)) {
            // For multi-query results return the last non-empty result
            foreach (array_reverse($this->multiqueryResults) as $rows) {
                if (! empty($rows)) {
                    return $rows;
                }
            }
        } else {
            $rs = $query->getResultResource();
            if (! ($rs instanceof \mysqli_result)) {
                return [];
            }
            while ($row = mysqli_fetch_assoc($rs)) {
                $array[] = $row;
            }
        }
        return $array;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::getInsertId()
     */
    public function getInsertId(SqlDataQuery $query)
    {
        try {
            // According to PHP docs, this returns the ID generated by an INSERT or UPDATE query on a table with a 
            // column having the AUTO_INCREMENT attribute. In the case of a multiple-row INSERT statement, it returns 
            // the first automatically generated value that was successfully inserted.
            // @link https://www.php.net/manual/en/mysqli.insert-id.php
            $lastAutoIncrement = mysqli_insert_id($this->getCurrentConnection());
            // Note, mysqli_insert_id returns zero if there was no previous query on the connection or if the query did 
            // not update an AUTO_INCREMENT value. Since the DataSheet would interpret the integer `0` as a valid key
            // value, we replace it with `null` here.
            if ($lastAutoIncrement === 0) {
                $lastAutoIncrement = null;
            }
        } catch (\mysqli_sql_exception $e) {
            throw new DataQueryFailedError($query, "Cannot get insert_id for SQL query: " . $e->getMessage(), '6T2TCAJ', $e);
        }
        return $lastAutoIncrement;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::getAffectedRowsCount()
     */
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::transactionStart()
     */
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::transactionCommit()
     */
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::transactionRollback()
     */
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::freeResult()
     */
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::escapeString()
     */
    public function escapeString(string $string) : string
    {
        return mysqli_real_escape_string($this->getCurrentConnection(), $string);
    }

    protected function getSslCaCertificatePath() : ?string
    {
        return $this->sslCACertificatePath;
    }

    /**
     * Path to the certificate authority .pem file - either absolute or relative to workbench folder
     * 
     * See https://www.php.net/manual/en/mysqli.ssl-set.php for details.
     * 
     * @uxon-property ssl_ca_certificate_path
     * @uxon-type string
     * 
     * @param string $sslCertificatePath
     * @return \exface\Core\DataConnectors\MySqlConnector
     */
    protected function setSslCaCertificatePath(string $sslCertificatePath) : MySqlConnector
    {
        $this->sslCACertificatePath = $sslCertificatePath;
        return $this;
    }

    protected function getSslCertificatePath() : ?string
    {
        return $this->sslCertificatePath;
    }

    /**
     * Path path name to the certificate .pem file - either absolute or relative to workbench folder
     * 
     * See https://www.php.net/manual/en/mysqli.ssl-set.php for details.
     * 
     * @uxon-property ssl_certificate_path
     * @uxon-type string
     * 
     * @param string $sslCertificatePath
     * @return \exface\Core\DataConnectors\MySqlConnector
     */
    protected function setSslCertificatePath(string $sslCertificatePath) : MySqlConnector
    {
        $this->sslCertificatePath = $sslCertificatePath;
        return $this;
    }

    protected function getSslKey() : ?string
    {
        return $this->sslKey;
    }

    /**
     * Path SSL private key file - either absolute or relative to workbench folder
     * 
     * See https://www.php.net/manual/en/mysqli.ssl-set.php for details.
     * 
     * @uxon-property ssl_key
     * @uxon-type string
     * 
     * @param string $sslKey
     * @return \exface\Core\DataConnectors\MySqlConnector
     */
    protected function setSslKey(string $sslKey) : MySqlConnector
    {
        $this->sslKey = $sslKey;
        return $this;
    }

    protected function getSslCaPath() : ?string
    {
        return $this->sslCaPath;
    }

    /**
     * Path to a directory that contains trusted SSL CA certificates in PEM format - either absolute or relative to workbench folder
     * 
     * See https://www.php.net/manual/en/mysqli.ssl-set.php for details.
     * 
     * @uxon-property ssl_ca_path
     * @uxon-type string
     * 
     * @param string $sslCaPath
     * @return \exface\Core\DataConnectors\MySqlConnector
     */
    protected function setSslCaPath(string $sslCaPath) : MySqlConnector
    {
        $this->sslCaPath = $sslCaPath;
        return $this;
    }

    protected function getSslCipherAlgos() : ?string
    {
        return $this->sslCipherAlgos;
    }

    /**
     * A list of allowable ciphers to use for SSL encryption
     * 
     * See https://www.php.net/manual/en/mysqli.ssl-set.php for details.
     * 
     * @uxon-property ssl_cipher_algos
     * @uxon-type string
     * 
     * @param string $sslCipherAlgos
     * @return \exface\Core\DataConnectors\MySqlConnector
     */
    protected function setSslCipherAlgos(string $sslCipherAlgos) : MySqlConnector
    {
        $this->sslCipherAlgos = $sslCipherAlgos;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    protected function isSslEnabled() : bool
    {
        return $this->getSslCertificatePath() !== null || $this->getSslCaCertificatePath() || $this->getSslCaPath();
    }
}