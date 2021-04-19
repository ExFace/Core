<?php
namespace exface\Core\DataConnectors;

use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionTransactionStartError;
use exface\Core\Exceptions\DataSources\DataConnectionCommitFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionRollbackFailedError;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\ModelBuilders\MsSqlModelBuilder;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Exceptions\DataQueryExceptionInterface;
use exface\Core\Exceptions\DataSources\DataQueryConstraintError;

/**
 * Microsoft SQL Server connector via sqlsrv PHP extension.
 *
 * @author Andrej Kabachnik
 *        
 */
class MsSqlConnector extends AbstractSqlConnector
{

    private $dBase = null;
    
    private $warningsReturnAsErrors = false;
    
    private $resultCounter = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        $connectInfo = array();
        $connectInfo["Database"] = $this->getDatabase();
        $connectInfo["CharacterSet"] = $this->getCharacterSet();
        $connectInfo['ReturnDatesAsStrings'] = true;
        if ($this->getUID()) {
            $connectInfo["UID"] = $this->getUID();
        }
        if ($this->getPWD()) {
            $connectInfo["PWD"] = $this->getPWD();
        }
        
        if (function_exists('sqlsrv_connect') === false) {
            throw new DataConnectionFailedError($this, 'PHP function "sqlsrv_connect" not available!', '76BJXFH');
        }
        
        if ($this->getWarningsReturnAsErrors() === false) {
            if(sqlsrv_configure("WarningsReturnAsErrors", 0) === false) {
                throw new DataConnectionFailedError($this, 'PHP function "sqlsrv_connect" not available!', '76BJXFH');
            } 
        }
        
        if (! $conn = sqlsrv_connect($this->getServerName() . ($this->getPort() ? ', ' . $this->getPort() : ''), $connectInfo)) {
            throw new DataConnectionFailedError($this, "Failed to create the database connection! " . $this->getLastError());
        } else {
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
        @ sqlsrv_close($this->getCurrentConnection());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     *
     * @param SqlDataQuery $query            
     */
    protected function performQuerySql(SqlDataQuery $query)
    {
        $sql = $query->getSql();
        $this->resultCounter = null;
        if ($query->isMultipleStatements()) {
            $stmtNo = 1;
            $this->resultCounter = 0;
            
            $stmt = sqlsrv_query($this->getCurrentConnection(), $sql);
            if ($stmt === false) {
                throw $this->createQueryError($query, "SQL multi-query statement {$stmtNo} failed! " . $this->getLastError());
            } else {
                $query->setResultResource($stmt);
            }
            
            // Consume the first result without calling sqlsrv_next_result.
            $this->resultCounter = sqlsrv_rows_affected($stmt);
            
            // Move to the next result and display results.
            $next_result = sqlsrv_next_result($stmt);
            while ($next_result === true) {
                $stmtNo++;
                $next_result = sqlsrv_next_result($stmt);
                $this->resultCounter += sqlsrv_rows_affected($stmt);
            }
            if($next_result === false) {
                throw $this->createQueryError($query, "SQL multi-query statement {$stmtNo} failed! " . $this->getLastError());
            }
        } else {
            if (StringDataType::startsWith($sql, 'INSERT', false) === true) {
                $sql .= '; SELECT SCOPE_IDENTITY() AS IDENTITY_COLUMN_NAME';
            }
            if (! $result = sqlsrv_query($this->getCurrentConnection(), $sql)) {
                throw $this->createQueryError($query, "SQL query failed! " . $this->getLastError());
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
    protected function createQueryError(DataQueryInterface $query, string $message) : DataQueryExceptionInterface
    {
        $sqlErrorNo = $this->getLastErrorCode();
        
        switch ($sqlErrorNo) {
            case 2627:
            case 2601:
                return new DataQueryConstraintError($query, $message, '73II64M');
            default:
                return new DataQueryFailedError($query, $message, '6T2T2UI');
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
                if ($err = $this->getLastError()) {
                    throw new DataQueryFailedError($query, "Cannot count affected rows in SQL query: " . $err, '6T2TCL6');
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
     * @return string|NULL
     */
    protected function getLastError() : ?string
    {
        $errors = $this->getErrors();
        return $errors[0]['message'];
    }
    
    /**
     * 
     * @return int|string|NULL
     */
    protected function getLastErrorCode()
    {
        $errors = $this->getErrors();
        return $errors[0]['code'];
    }

    protected function getErrors()
    {
        if ($this->getWarningsReturnAsErrors()) {
            return sqlsrv_errors();
        } else {
            return sqlsrv_errors(SQLSRV_ERR_ERRORS);
        }
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
        if (! $rs)
            return array();
        $array = array();
        while ($row = sqlsrv_fetch_array($rs, SQLSRV_FETCH_ASSOC)) {
            $array[] = $row;
        }
        return $array;
    }

    public function transactionStart()
    {
        // Do nothing if the autocommit option is set for this connection
        if ($this->getAutocommit()) {
            return $this;
        }
        
        // Make sure, the connection is established
        if (! $this->isConnected()) {
            $this->connect();
        }
        if (! sqlsrv_begin_transaction($this->getCurrentConnection())) {
            throw new DataConnectionTransactionStartError($this, 'Cannot start transaction in "' . $this->getAliasWithNamespace() . '": ' . $this->getLastError(), '6T2T2JM');
        } else {
            $this->setTransactionStarted(true);
        }
        return $this;
    }

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
            throw new DataConnectionCommitFailedError($this, 'Cannot commit transaction in "' . $this->getAliasWithNamespace() . '": ' . $this->getLastError(), '6T2T2O9');
        } else {
            $this->setTransactionStarted(false);
        }
        return $this;
    }

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
            throw new DataConnectionRollbackFailedError($this, $this->getLastError(), '6T2T2S1');
        } else {
            $this->setTransactionStarted(false);
        }
        return $this;
    }

    public function freeResult(SqlDataQuery $query)
    {
        if (is_resource($query->getResultResource())) {
            sqlsrv_free_stmt($query->getResultResource());
        }
    }

    public function getUID()
    {
        return $this->getUser();
    }

    /**
     * SQL Server user id (same as "user")
     *
     * @uxon-property UID
     * @uxon-type string
     *
     * @see set_user()
     * @param string $value            
     * @return MsSqlConnector
     */
    public function setUID($value)
    {
        return $this->setUser($value);
    }

    public function getPWD()
    {
        return $this->getPassword();
    }

    /**
     * The password for the connection (same as "password")
     *
     * @uxon-property PWD
     * @uxon-type password
     *
     * @see set_password()
     * @param string $value            
     * @return MsSqlConnector
     */
    public function setPWD($value)
    {
        return $this->setPassword($value);
    }

    public function getServerName()
    {
        return $this->getHost();
    }

    /**
     * The server name for the connection (same as "host")
     *
     * @uxon-property serverName
     * @uxon-type string
     *
     * @see set_host()
     * @param string $value            
     * @return MsSqlConnector
     */
    public function setServerName($value)
    {
        return $this->setHost($value);
    }

    public function getDatabase()
    {
        return $this->dBase;
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
     * @uxon-property dbase
     * @uxon-type string
     *
     * @see set_database()
     * @param string $value
     * @return MySqlConnector
     */
    public function setDbase($value)
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
    
    /**
     * 
     * @return string|NULL
     */
    public function getCharset() : ?string
    {
        return $this->getCharacterSet();
    }
    
    /**
     * 
     * @return string
     */
    public function getDbase()
    {
        return $this->getDatabase();
    }
    
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
}