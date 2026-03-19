<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionTransactionStartError;
use exface\Core\Exceptions\DataSources\DataConnectionCommitFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionRollbackFailedError;
use exface\Core\Exceptions\DataSources\DataQueryConstraintError;
use exface\Core\Exceptions\DataSources\DataQueryForeignKeyError;
use exface\Core\Exceptions\DataSources\DataQueryUniqueConstraintError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\Exceptions\DataSources\PostgreSqlError;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Interfaces\Exceptions\DataQueryExceptionInterface;
use exface\Core\ModelBuilders\PostgreSqlModelBuilder;
use exface\Core\QueryBuilders\PostgreSqlBuilder;
use pgsql\Connection;
use PgSql\Result;

/**
 * Data source connector for PostgreSQL databases
 *
 * @author Andrej Kabachnik
 */
class PostgreSqlConnector extends AbstractSqlConnector
{
    private $dbase = null;
    private $use_persistant_connection = false;
    private $affectedRows = null;
    
    private array $sessionOptions = [];

    /**
     * Establishes a connection to the PostgreSQL database
     */
    protected function performConnect()
    {
        /*
         * Params described in https://www.php.net/manual/en/function.pg-connect.php
         * The currently recognized parameter keywords are: host, hostaddr, port, dbname (defaults to value of user), 
         * user, password, connect_timeout, options, tty (ignored), sslmode, requiressl (deprecated in favor of sslmode), 
         * and service. Which of these arguments exist depends on your PostgreSQL version.
         */
        $params = [
            'host=' . $this->getHost(),
            'user=' . $this->getUser(),
            'password=' . $this->getPassword()
        ];

        if ($this->getPort()) {
            $params[] = 'port=' . $this->getPort();
        }

        if ($this->getDbase()) {
            $params[] = 'dbname=' . $this->getDbase();
        }

        if ($this->getCharacterSet()) {
            $params[] = 'options=\'--client_encoding=' . $this->getCharacterSet() . '\'';
        }

        $connStr = implode(' ', $params);
        $conn = $this->getUsePersistantConnection()
            ? @pg_pconnect($connStr)
            : @pg_connect($connStr);

        if (! $conn instanceof Connection) {
            throw new DataConnectionFailedError($this, 'Failed to connect to PostgreSQL: ' . pg_last_error());
        }

        $this->setCurrentConnection($conn);
        
        foreach ($this->sessionOptions as $option => $value) {
            $result = pg_query($conn, "SET {$option} TO '{$value}'");
            if ($result === false) {
                throw new DataConnectionFailedError($this, 'Failed to set PostgreSQL session option "' . $option . '" to `\'' . $value . '\'`: ' . pg_last_error($conn));
            }
        }
    }

    /**
     * Disconnects from the PostgreSQL database
     */
    protected function performDisconnect()
    {
        if ($conn = $this->getCurrentConnection()) {
            @pg_close($conn);
            $this->resetCurrentConnection();
        }
    }

    /**
     * {@inheritDoc}
     * @see AbstractSqlConnector::performQuerySql()
     */
    protected function performQuerySql(SqlDataQuery $query)
    {
        $conn = $this->getCurrentConnection();
        $this->affectedRows = null;

        $sql = $query->getSql();

        // For single statements add ...RETURNING <pkey> to make sure auto-generated ids are returned
        if (! $query->isMultipleStatements()) {
            $pkeys = $query->getPrimaryKeyColumns();
            if (!empty($pkeys) && StringDataType::startsWith($sql, 'INSERT', false) === true) {
                $sql .= ' RETURNING ' . implode(', ', $pkeys);
            }
            // Save mutated SQL for logging
            $query->setSql($sql);
        } else {
            // For multi-statements we keep SQL as-is; pg_get_result will return one result per statement.
            // (No need to modify the original SQL here.)
        }

        // Send query asynchronously so we always can fetch a PgSql\Result to inspect error fields.
        // See https://www.php.net/manual/en/function.pg-result-error-field.php
        // See https://www.php.net/manual/uk/function.pg-get-result.php
        $sent = @pg_send_query($conn, $sql);
        if ($sent === false) {
            // If we couldn't even send, fall back to connection error text.
            throw $this->createQueryError($query, pg_last_error($conn));
        }

        $lastResult = null;
        $lastStatus = null;
        $allResults = [];

        // Drain all results (important for multi-statement and also vital for erroneous queries).
        // See https://www.php.net/manual/uk/function.pg-get-result.php
        // See https://stackoverflow.com/questions/12349230/catching-errors-from-postgresql-to-php
        while (($res = pg_get_result($conn)) !== false) {
            $allResults[] = $res;
            $lastResult = $res;
            $lastStatus = pg_result_status($res);
            if ($lastStatus === PGSQL_FATAL_ERROR || $lastStatus === PGSQL_NONFATAL_ERROR) {
                throw $this->createQueryError($query, pg_last_error($conn), $res);
            }
        }

        // If nothing came back, treat as unexpected / connection-level error.
        if ($lastResult === null) {
            throw $this->createQueryError($query, pg_last_error($conn));
        }
        
        // Handle main result resource; for multi-statement, last result matches pg_query() behavior.
        $this->affectedRows = pg_affected_rows($lastResult);
        $query->setResultResource($lastResult);

        // TODO preserve all statement results somewhere:
        // $query->setAllResultResources($allResults);

        return $query;
    }

    /**
     * 
     * @param DataQueryInterface $query
     * @param string $message
     * @return DataQueryExceptionInterface
     */
    protected function createQueryError(DataQueryInterface $query, string $message = null, ?Result $res = null) : DataQueryExceptionInterface
    {        
        $message = 'PostgreSQL query failed. ' . $message;
        $e = new PostgreSqlError($this, $message, '6T2T2UI', null, $res);
        $sqlState = intval($e->getSqlState());
        switch (true) {
            case $sqlState === PostgreSqlError::SQL_STATE_UNIQUE_VIOLATION:
                $obj = $e->getAffectedObject();
                $attrVals = $e->getAffectedAttributeValues();
                $e = new DataQueryUniqueConstraintError($query, $this, $message, null, $e, $obj, $attrVals);
                break;
            case $sqlState === PostgreSqlError::SQL_STATE_FOREIGN_KEY_VIOLATION:
                $obj = $e->getAffectedObject();
                $attrVals = $e->getAffectedAttributeValues();
                $e = new DataQueryForeignKeyError($query, $message, null, $e, $obj, $attrVals);
                break;
            case $sqlState === 23000: // INTEGRITY CONSTRAINT VIOLATION
            case $sqlState === 23514: // CHECK VIOLATION
            case $sqlState === 23001: // RESTRICT VIOLATION
            case $sqlState === 23502: // NOT NULL VIOLATION
                $obj = $e->getAffectedObject();
                $attrVals = $e->getAffectedAttributeValues();
                $e = new DataQueryConstraintError($query, $message, null, $e, $obj, $attrVals);
                break;
            default:
                $e = new DataQueryFailedError($query, $message, null, $e);
                break;
        }
        
        return $e;
    }

    /**
     * Converts result resource to array
     */
    public function makeArray(SqlDataQuery $query)
    {
        $array = [];
        $rs = $query->getResultResource();
        if (! $rs instanceof \pgsql\Result) {
            return [];
        }

        while ($row = pg_fetch_assoc($rs)) {
            $array[] = $row;
        }

        return $array;
    }

    public function getInsertId(SqlDataQuery $query)
    {
        $id = "";
        $result = $query->getResultResource();
        if ($result) {
            $row = $query->getResultArray()[0];
            $pkeys = $query->getPrimaryKeyColumns();
            switch (count($pkeys)) {
                case 0:
                    return $row[array_key_first($row)];
                case 1:
                    return $row[$pkeys[0]];
                // TODO what about compound keys???
            }
        }
        return $id;
    }

    public function getAffectedRowsCount(SqlDataQuery $query)
    {
        return $this->affectedRows;
    }

    public function freeResult(SqlDataQuery $query)
    {
        if (is_resource($query->getResultResource())) {
            pg_free_result($query->getResultResource());
        }
    }

    public function transactionStart()
    {
        if (!$this->transactionIsStarted()) {
            if (!$this->isConnected()) {
                $this->connect();
            }

            if (!pg_query($this->getCurrentConnection(), 'BEGIN')) {
                throw new DataConnectionTransactionStartError($this, 'Failed to start transaction: ' . pg_last_error($this->getCurrentConnection()));
            }

            $this->setTransactionStarted(true);
        }

        return $this;
    }

    public function transactionCommit()
    {
        if ($this->getAutocommit()) {
            return $this;
        }

        if (!pg_query($this->getCurrentConnection(), 'COMMIT')) {
            throw new DataConnectionCommitFailedError($this, 'Failed to commit transaction: ' . pg_last_error($this->getCurrentConnection()));
        }

        $this->setTransactionStarted(false);
        return $this;
    }

    public function transactionRollback()
    {
        if ($this->getAutocommit()) {
            throw new DataConnectionRollbackFailedError($this, 'Cannot rollback: autocommit is enabled.');
        }

        if (!pg_query($this->getCurrentConnection(), 'ROLLBACK')) {
            throw new DataConnectionRollbackFailedError($this, 'Failed to rollback transaction: ' . pg_last_error($this->getCurrentConnection()));
        }

        $this->setTransactionStarted(false);
        return $this;
    }

    public function getDbase()
    {
        return $this->dbase;
    }

    /**
     * The database name to connect to
     *
     * @uxon-property dbase
     * @uxon-type string
     *
     * @param string $value
     * @return PostgreSqlConnector
     */
    public function setDbase($value)
    {
        $this->dbase = $value;
        return $this;
    }

    public function getUsePersistantConnection()
    {
        return $this->use_persistant_connection;
    }

    /**
     * Set to TRUE to use persistent connections
     *
     * @uxon-property use_persistant_connection
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $value
     * @return PostgreSqlConnector
     */
    public function setUsePersistantConnection($value)
    {
        $this->use_persistant_connection = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('dbase', $this->getDbase());
        $uxon->setProperty('use_persistant_connection', $this->getUsePersistantConnection());
        return $uxon;
    }

    /**
     * {@inheritdoc}
     */
    public function escapeString(string $string): string
    {
        return pg_escape_string($this->getCurrentConnection(), $string);
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getModelBuilder()
     */
    public function getModelBuilder()
    {
        return new PostgreSqlModelBuilder($this);
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getSqlDialect()
     */
    public function getSqlDialect(): string
    {
        return PostgreSqlBuilder::SQL_DIALECT_PGSQL;
    }

    /**
     * @return string[]
     */
    protected function getSessionOptions() : array
    {
        return $this->sessionOptions;
    }

    /**
     * Set PostgreSQL session variables via `SET ... TO '...'`
     * 
     * @uxon-property session_options
     * @uxon-type object
     * @uxon-template {"lc_messages": "en_US.UTF-8"}
     * 
     * @param UxonObject $arrayOfOptions
     * @return PostgreSqlConnector
     */
    protected function setSessionOptions(UxonObject $arrayOfOptions) : PostgreSqlConnector
    {
        $this->sessionOptions = $arrayOfOptions->toArray();
        return $this;
    }
}