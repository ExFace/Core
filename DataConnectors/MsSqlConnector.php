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

/**
 * Microsoft SQL Server connector via sqlsrv PHP extension.
 *
 * @author Andrej Kabachnik
 *        
 */
class MsSqlConnector extends AbstractSqlConnector
{

    private $Database = null;

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
        if (StringDataType::startsWith($sql, 'INSERT', false) === true) {
            $sql .= '; SELECT SCOPE_IDENTITY() AS IDENTITY_COLUMN_NAME';
        }
        if (! $result = sqlsrv_query($this->getCurrentConnection(), $sql)) {
            throw new DataQueryFailedError($query, "SQL query failed! " . $this->getLastError(), '6T2T2UI');
        } else {
            $query->setResultResource($result);
            return $query;
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
        $cnt = sqlsrv_rows_affected($this->getCurrentConnection());
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

    protected function getLastError()
    {
        $errors = $this->getErrors();
        return $errors[0]['message'];
    }

    protected function getErrors()
    {
        return sqlsrv_errors();
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
        return $this->DataBase;
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
        $this->DataBase = $value;
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
}
?>