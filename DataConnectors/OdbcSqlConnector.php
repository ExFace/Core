<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionCommitFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionRollbackFailedError;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;

/**
 * Generic connector for ODBC SQL data sources
 *
 * @author Andrej Kabachnik
 */
class OdbcSqlConnector extends AbstractSqlConnector
{

    private $dsn = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        $conn = null;
        $conn_exception = null;
        
        // Connect
        try {
            $conn = @odbc_connect($this->getDsn(), $this->getUser(), $this->getPassword(), SQL_CUR_USE_ODBC);
        } catch (\Exception $e) {
            $conn = 0;
            $conn_exception = $e;
        }
        
        if (! $conn) {
            throw new DataConnectionFailedError($this, 'Failed to create the database connection for "' . $this->getAliasWithNamespace() . '"!', '6T2TBVR', $conn_exception);
        }
        
        // Apply autocommit option
        if ($this->getAutocommit()) {
            odbc_autocommit($conn, 1);
        } else {
            odbc_autocommit($conn, 0);
        }
        
        $this->setCurrentConnection($conn);
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
            @odbc_close($this->getCurrentConnection());
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
            $result = odbc_exec($this->getCurrentConnection(), $query->getSql());
            $query->setResultResource($result);
        } catch (\Exception $e) {
            throw new DataQueryFailedError($query, "ODBC SQL query failed! " . $e->getMessage(), '6T2T2UI', $e);
        }
        return $query;
    }

    protected function getLastError()
    {
        return odbc_error($this->getCurrentConnection()) . ' (' . odbc_errormsg($this->getCurrentConnection() . ')');
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
        while ($row = odbc_fetch_array($rs)) {
            $array[] = $row;
        }
        return $array;
    }

    public function getInsertId(SqlDataQuery $query)
    {
        try {
            // TODO
        } catch (\Exception $e) {
            throw new DataQueryFailedError($query, "Cannot get insert_id for SQL query: " . $e->getMessage(), '6T2TCAJ', $e);
        }
    }

    public function getAffectedRowsCount(SqlDataQuery $query)
    {
        try {
            return odbc_num_rows($query->getResultResource());
        } catch (\Exception $e) {
            throw new DataQueryFailedError($query, "Cannot count affected rows in ODBC SQL query: " . $e->getMessage(), '6T2TCL6', $e);
        }
    }

    public function transactionStart()
    {
        if (! $this->transactionIsStarted()) {
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
        
        try {
            return odbc_commit($this->getCurrentConnection());
        } catch (\Exception $e) {
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
            return odbc_rollback($this->getCurrentConnection());
        } catch (\Exception $e) {
            throw new DataConnectionRollbackFailedError($this, "Rollback failed: " . $e->getMessage(), '6T2T2S1', $e);
        }
        return $this;
    }

    public function freeResult(SqlDataQuery $query)
    {
        odbc_free_result($query->getResultResource());
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

    public function getDsn()
    {
        return $this->dsn;
    }

    /**
     * Sets the DSN to be used in the ODBC connection
     *
     * @uxon-property dsn
     * @uxon-type string
     *
     * @param string $value            
     * @return OdbcSqlConnector
     */
    public function setDsn($value)
    {
        $this->dsn = $value;
        return $this;
    }
}
?>