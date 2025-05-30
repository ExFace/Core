<?php
namespace exface\Core\DataConnectors;

use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionTransactionStartError;
use exface\Core\Exceptions\DataSources\DataConnectionCommitFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionRollbackFailedError;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\Exceptions\DataSources\MsSqlError;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\ModelBuilders\MsSqlModelBuilder;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Exceptions\DataQueryExceptionInterface;
use exface\Core\Exceptions\DataSources\DataQueryConstraintError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSources\DataQueryRelationCardinalityError;

/**
 * Microsoft SQL Server connector via the official sqlsrv PHP extension.
 * 
 * Apart from the typical SQL connection config syntax you can also explicitly set the `connection_options` 
 * as described here: https://docs.microsoft.com/en-us/sql/connect/php/connection-options.
 * 
 * Also note, that the connector handles SQL server warnings as errors by default. If you wish to suppress
 * error messages for warnings, set `warnings_return_as_errors` to `false`. See the official documentation
 * for more details: https://www.php.net/manual/en/function.sqlsrv-configure.php.
 * 
 * ## Connection types and authentication
 * 
 * ### SQL Server authentication (user+password)
 * 
 * The simplest way to connect to an SQL Server is to use a local user configured inside the SQL Server.
 * The downside is, that this user will not be visible to any centra authority like ActiveDirectory, etc.
 * 
 * **Security concerns:** The password is stored encrypted in the metamodel, but if the DB connection is 
 * defined in the `System.config.json`, the password will be stored as plain text on the file system, 
 * which may be a security risc! The encrypted password will also be decrypted and shown to users that 
 * can edit the connection configuration.
 * 
 * ```
 * {
 *  "host": "<NETWORKNAME/INSTANCE or localhost>",
 *  "user": "<SQL Server user>",
 *  "password": "<password>"
 * }
 * 
 * ```
 * 
 * ### Windows authentication
 * 
 * Another common way to connect using a Windows user (typically managed by ActiveDirectory). In this case,
 * the authentication is actually done by the web server, not by the workbench. This is the preferred option
 * for metamodel connections when using the IIS as server since the password must not be stored in the
 * configuration of the workbench.
 * 
 * ```
 * {
 *  "host": "<NETWORKNAME/INSTANCE or localhost>"
 * }
 * 
 * ```
 * 
 * **IMPORTANT:** the PHP process must run as the user you need to authenticate with. Depending on the web
 * server used, different approaches are possible.
 * 
 * In the case of Microsoft IIS, the workbench needs to be installed in a "Virtual folder" in one of the
 * IIS application pools. The configuration of the pool seems not important, but in the settings of the
 * virtual folder, you need to specify the user and password:
 * 
 *  1. Open IIS Manager
 *  2. Navigate to `<servername> > Sites > Default Web Site` on the left panel
 *  3. Find the virtual directory, where the workbencnh is installed, beneath the selected web site
 *  4. Select the virtual directory and press `Basic settings` on the right panel
 *  5. Press `Connect as...`
 *  6. Select `Specific user` and press `Set...` right next to it
 *  7. Type the user name with domain like `MYDOMAIN\User name` and that users current password
 * 
 * The workbench must be installed within the folder above. If you need to change the password, select your
 * created virtual directory on the left panel and press `Basic settings` on the right panel under `Actions`.
 * 
 * ### Azure AD authentication
 * 
 * See https://learn.microsoft.com/en-us/sql/connect/php/azure-active-directory?view=sql-server-ver16
 * 
 * ### Azure KeyVault authentication
 * 
 * See https://learn.microsoft.com/en-us/sql/connect/php/using-always-encrypted-php-drivers?view=sql-server-ver16#using-azure-key-vault
 * 
 * ### Force secure connections (SSL)
 * 
 * ```
 * {
 *  "host": "<NETWORKNAME/INSTANCE or localhost>"
 *  "connection_options": {
 *      "Encrypt": true,
 *      "TrustServerCertificate": true
 *  }
 * }
 * 
 * ```
 * 
 * ## Testing the connection
 * 
 * Place the folloing code into a *.php file on the server to test the connection independently from the workbench.
 * 
 * ## Troubleshooting
 * 
 * @link https://github.com/ExFace/Core/tree/1.x-dev/Docs/creating_metamodels/data_sources/SQL/Troubleshooting_MS_SQL.md
 * 
 * ```
<?php
    $serverName = "<SERVER>\<INSTANCE>";  
    $connectionInfo = [
    	"Database" => "<dbname>"
    	// Add more options here
    ];  
      
    $conn = sqlsrv_connect($serverName, $connectionInfo);  
    if( $conn === false ) {  
         echo "Unable to connect.</br>";  
         die( print_r( sqlsrv_errors(), true));  
    }  
    
    $tsql = "SELECT CONVERT(varchar(32), SUSER_SNAME())";  
    $stmt = sqlsrv_query( $conn, $tsql);  
    if( $stmt === false )  {  
         echo "Error in executing query.</br>";  
         die( print_r( sqlsrv_errors(), true));  
    }  
     
    $row = sqlsrv_fetch_array($stmt);  
    echo "User login: ".$row[0]."</br>";  
      
    sqlsrv_free_stmt( $stmt);  
    sqlsrv_close( $conn);  
?>
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *
 */
class MsSqlConnector extends AbstractSqlConnector
{
    private $connectionInfo = [];
    
    private $dBase = null;
    
    private $warningsReturnAsErrors = false;
    
    private $resultCounter = null;
    
    private $multiqueryResults = null;
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        $connectInfo = $this->getConnectionOptions();
        $connectInfo["Database"] = $this->getDatabase();
        if (null !== $charset = $this->getCharacterSet()) {
            $connectInfo["CharacterSet"] = $charset;
        }
        $connectInfo['ReturnDatesAsStrings'] = $this->getConnectionOptions()['ReturnDatesAsStrings'] ?? true;
        if ($this->getUser()) {
            $connectInfo["UID"] = $this->getUser();
        }
        if ($this->getPassword()) {
            // Escape closing curly braces in MS SQL password by a second brace
            $connectInfo["PWD"] = preg_replace('/([^}])}([^}])/', '\\1}}\\2', $this->getPassword());
        }
        
        if (function_exists('sqlsrv_connect') === false) {
            throw new DataConnectionFailedError($this, 'MS SQL Server drivers for PHP (extension "sqlsrv") not installed!', '76BJXFH');
        }
        
        if ($this->getWarningsReturnAsErrors() === false) {
            if(sqlsrv_configure("WarningsReturnAsErrors", 0) === false) {
                throw new DataConnectionFailedError($this, 'Failed to set configuration for MS SQL Server connection.');
            }
        }
        
        if (! $conn = sqlsrv_connect($this->getHost() . ($this->getPort() ? ', ' . $this->getPort() : ''), $connectInfo)) {
            $e = $this->getLastErrorException();
            throw new DataConnectionFailedError($this, "Failed to connect to MS SQL Server database. " . ($e ? $e->getMessage() : 'Unknown error'), null, $e->setAlias('7ZBVB1G'));
        } else {
            $this->setCurrentConnection($conn);
        }
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performDisconnect()
     */
    protected function performDisconnect()
    {
        if (($conn = $this->getCurrentConnection()) !== null) {
            @sqlsrv_close($conn);
            $this->resetCurrentConnection();
        }
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     *
     * @param SqlDataQuery $query
     */
    protected function performQuerySql(SqlDataQuery $query)
    {
        $sql = $query->getSql();
        $this->resultCounter = null;
        if ($query->isMultipleStatements()) {
            $stmtNo = 0;
            $this->resultCounter = 0;
            $this->multiqueryResults = [];

            foreach ($query->getSqlBatches() as $batchNo => $sql) {
                $stmt = sqlsrv_query($this->getCurrentConnection(), $sql);
                if ($stmt === false) {
                    throw $this->createQueryError($query, 'SQL multi-query statement ' . ($stmtNo + 1) . ($batchNo > 0 ? ' in batch ' . $batchNo+1 : '') . ' failed!');
                } else {
                    $query->setResultResource($stmt);
                }
                
                // Consume the first result without calling sqlsrv_next_result.
                $this->resultCounter += max(sqlsrv_rows_affected($stmt), 0);
                $this->multiqueryResults[$stmtNo] = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $this->multiqueryResults[$stmtNo][] = $row;
                }
                
                // Move to the next result and display results.
                $next_result = sqlsrv_next_result($stmt);
                while ($next_result === true) {
                    $stmtNo++;
                    $next_result = sqlsrv_next_result($stmt);
                    $this->resultCounter += max(sqlsrv_rows_affected($stmt), 0);
                    $this->multiqueryResults[$stmtNo] = [];
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $this->multiqueryResults[$stmtNo][] = $row;
                    }
                }
                if($next_result === false) {
                    throw $this->createQueryError($query, 'SQL multi-query statement ' . ($stmtNo+1) . ' failed!');
                }
            }
        } else {
            if (StringDataType::startsWith($sql, 'INSERT', false) === true) {
                $sql .= '; SELECT SCOPE_IDENTITY() AS IDENTITY_COLUMN_NAME';
            }
            if (! $result = sqlsrv_query($this->getCurrentConnection(), $sql)) {
                throw $this->createQueryError($query, "SQL query failed!");
            } else {
                $query->setResultResource($result);
            }
        }
        return $query;
    }
    
    /**
     *
     * @param DataQueryInterface $query
     * @param string $message
     * @return DataQueryExceptionInterface
     */
    protected function createQueryError(DataQueryInterface $query, string $message = null) : DataQueryExceptionInterface
    {
        $err = $this->getLastErrorException();
        if ($message === null) {
            $message = $err->getMessage();
        } else {
            $message = StringDataType::endSentence($message) . ' SQL error: ' . $err->getMessage();
        }
        
        switch ($err->getSqlErrorCode()) {
            case 2627:
            case 2601:
                return new DataQueryConstraintError($query, $message, null, $err->setAlias('73II64M'));
            // Subquery returns more than 1 row - SQL error code 1242
            case 1242:
                return new DataQueryRelationCardinalityError($query, $message, $err);
            default:
                return new DataQueryFailedError($query, $message, null, $err->setAlias('6T2T2UI'));
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::getInsertId()
     */
    public function getInsertId(SqlDataQuery $query)
    {
        $id = "";
        $resource = $query->getResultResource();
        if ($resource) {
            sqlsrv_next_result($resource);
            sqlsrv_fetch($resource);
            $id = sqlsrv_get_field($resource, 0);
        }
        return $id;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::getAffectedRowsCount()
     */
    function getAffectedRowsCount(SqlDataQuery $query)
    {
        if (! $stmt = $query->getResultResource()) {
            return null;
        }
        if ($this->resultCounter !== null) {
            return $this->resultCounter;
        }
        $cnt = sqlsrv_rows_affected($stmt);
        // sqlsrv_rows_affected() can return FALSE in case of an error accoring to the docs and -1
        // if no counting was possible.
        switch (true) {
            case $cnt === false:
                if ($err = $this->getLastErrorException()) {
                    throw new DataQueryFailedError($query, "Cannot count affected rows in SQL query: " . $err->getMessage(), null, $err->setAlias('6T2TCL6'));
                } else {
                    return null;
                }
            case $cnt === -1:
                return null;
        }
        return $cnt;
    }

    /**
     * 
     * @return MsSqlError
     */
    protected function getLastErrorException() : MsSqlError
    {
        return new MsSqlError($this, null);
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
        $array = [];
        if (! $rs) {
            return $array;
        }
        if ($query->isMultipleStatements() && ! empty($this->multiqueryResults)) {
            // For multi-query results return the last non-empty result
            foreach (array_reverse($this->multiqueryResults) as $rows) {
                if (! empty($rows)) {
                    return $rows;
                }
            }
        } else {
            while ($row = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) {
                $array[] = $row;
            }
        }
        return $array;
    }
    
    public function transactionStart()
    {
        // Do nothing if the autocommit option is set for this connection
        if ($this->getAutocommit()) {
            return $this;
        }
        
        if (! $this->transactionIsStarted()) {
            // Make sure, the connection is established
            if (! $this->isConnected()) {
                $this->connect();
            }
            if (! sqlsrv_begin_transaction($this->getCurrentConnection())) {
                $e = $this->getLastErrorException();
                throw new DataConnectionTransactionStartError($this, 'Cannot start transaction in "' . $this->getAliasWithNamespace() . '": ' . ($e ? $e->getMessage() : 'Unknown error'), null, $e->setAlias('6T2T2JM'));
            } else {
                $this->setTransactionStarted(true);
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
        
        // Do nothing if no transaction was started - there is nothing to commit.
        if ($this->transactionIsStarted() === false) {
            return $this;
        }
        
        if (! sqlsrv_commit($this->getCurrentConnection())) {
            $e = $this->getLastErrorException();
            throw new DataConnectionCommitFailedError($this, 'Cannot commit transaction in "' . $this->getAliasWithNamespace() . '": ' . ($e ? $e->getMessage() : 'Unknown error'), null, $e->setAlias('6T2T2O9'));
        } else {
            $this->setTransactionStarted(false);
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
        
        // Do nothing if no transaction was started - no changes to roll back.
        if ($this->transactionIsStarted() === false) {
            return $this;
        }
        
        if (! sqlsrv_rollback($this->getCurrentConnection())) {
            $e = $this->getLastErrorException();
            throw new DataConnectionRollbackFailedError($this, ($e ? $e->getMessage() : 'Unknown error'), null, $e->setAlias('6T2T2S1'));
        } else {
            $this->setTransactionStarted(false);
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
        if (is_resource($query->getResultResource())) {
            sqlsrv_free_stmt($query->getResultResource());
        }
    }
    
    /**
     * SQL Server user id (same as "user")
     *
     * @deprecated use setUser()
     * @param string $value
     * @return MsSqlConnector
     */
    protected function setUID($value)
    {
        return $this->setUser($value);
    }
    
    /**
     * The password for the connection (same as "password")
     *
     * @deprecated use setPassword()
     * @param string $value
     * @return MsSqlConnector
     */
    protected function setPWD($value)
    {
        return $this->setPassword($value);
    }
    
    /**
     * The server name for the connection (same as "host")
     *
     * @deprecated use setHost()
     * @param string $value
     * @return MsSqlConnector
     */
    protected function setServerName($value)
    {
        return $this->setHost($value);
    }
    
    public function getDatabase()
    {
        return $this->dBase ?? $this->getConnectionOptions()['Database'];
    }
    
    /**
     * The database to connect to
     *
     * @uxon-property database
     * @uxon-type string
     *
     * @param string $value
     * @return \exface\Core\DataConnectors\MsSqlConnector
     */
    public function setDatabase($value)
    {
        $this->dBase = $value;
        return $this;
    }
    
    /**
     * The database name to connect to (same as "database")
     *
     * @deprecated use setDatabase()
     * @param string $value
     * @return MySqlConnector
     */
    protected function setDbase($value)
    {
        return $this->setDatabase($value);
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
        $uxon->setProperty('Database', $this->getDatabase());
        return $uxon;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getModelBuilder()
     */
    public function getModelBuilder()
    {
        return new MsSqlModelBuilder($this);
    }
    
    /**
     * The character set to be used in this connection (same as "character_set")
     *
     * @deprecated use setCharacterSet()
     * @param string $value
     * @return MySqlConnector
     */
    protected function setCharset($value)
    {
        return $this->setCharacterSet($value);
    }
    
    /**
     *
     * @return bool
     */
    protected function getWarningsReturnAsErrors() : bool
    {
        return $this->warningsReturnAsErrors;
    }
    
    /**
     * Set to TRUE to make the connection throw exceptions on SQL Server warning messages.
     *
     * See https://docs.microsoft.com/en-us/sql/connect/php/how-to-handle-errors-and-warnings-using-the-sqlsrv-driver
     * for details.
     *
     * @uxon-property warnings_return_as_errors
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return MsSqlConnector
     */
    public function setWarningsReturnAsErrors(bool $value) : MsSqlConnector
    {
        $this->warningsReturnAsErrors = $value;
        return $this;
    }
    
    /**
     * 
     * @return array
     */
    protected function getConnectionOptions() : array
    {
        return $this->connectionInfo;
    }
    
    /**
     * SQL server PHP driver connection options for advanced configuration
     * 
     * See https://docs.microsoft.com/en-us/sql/connect/php/connection-options for details.
     * 
     * @uxon-property connection_options
     * @uxon-type object
     * @uxon-template {"":""}
     * 
     * @link https://docs.microsoft.com/en-us/sql/connect/php/connection-options
     * @param UxonObject|array $value
     * @return MsSqlConnector
     */
    public function setConnectionOptions($value) : MsSqlConnector
    {
        if ($value instanceof UxonObject) {
            $opts = $value->toArray();
        } else {
            $opts = $value;
        }
        
        foreach ($opts as $opt => $val) {
            switch ($opt) {
                case 'TransactionIsolation':
                    $opts[$opt] = constant($val);
                    break;
            }
        }
        
        $this->connectionInfo = $opts;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getCharacterSet()
     */
    public function getCharacterSet() : ?string
    {
        return parent::getCharacterSet() ?? $this->getConnectionOptions()['CharacterSet'];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::escapeString()
     */
    public function escapeString(string $string) : string
    {
        if (is_numeric($string)) return $string;
        
        // Remove invisible ASCII control chars like \x00 (NUL), etc.
        $toRemove = [
            '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/',             // url encoded 16-31
            '/[\x00-\x08]/',            // 00-08
            '/\x0b/',                   // 11
            '/\x0c/',                   // 12
            '/[\x0e-\x1f]/'             // 14-31
        ];
        foreach ($toRemove as $regex) {
            $string = preg_replace($regex, '', $string );
        }
        // Escape single quotes with another single quote
        $string = str_replace("'", "''", $string );
        
        return $string;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getBatchDelimiterPatter()
     */
    protected function getBatchDelimiterPattern() : ?string
    {
        return '/^GO;?/m';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::canJoin()
     */
    public function canJoin(DataConnectionInterface $otherConnection) : bool
    {
        $parentResult = parent::canJoin($otherConnection);
        if ($parentResult === false) {
            return false;
        }
        if (! $otherConnection instanceof $this) {
            return false;
        }
        return $this->getDatabase() === $otherConnection->getDatabase();
    }
}