<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionTransactionStartError;
use exface\Core\Exceptions\DataSources\DataConnectionCommitFailedError;
use exface\Core\Exceptions\DataSources\DataConnectionRollbackFailedError;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;
use exface\Core\ModelBuilders\PostgreSqlModelBuilder;
use exface\Core\QueryBuilders\PostgreSqlBuilder;

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

        if (! $conn instanceof \pgsql\Connection) {
            throw new DataConnectionFailedError($this, 'Failed to connect to PostgreSQL: ' . pg_last_error());
        }

        $this->setCurrentConnection($conn);
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
     * Executes a SQL query
     */
    protected function performQuerySql(SqlDataQuery $query)
    {
        $conn = $this->getCurrentConnection();
        $this->affectedRows = null;

        $sql = $query->getSql();
        if (! $query->isMultipleStatements()) {
            $pkeys = $query->getPrimaryKeyColumns();
            if (! empty($pkeys) && StringDataType::startsWith($sql, 'INSERT', false) === true) {
                $sql .= ' RETURNING ' . implode(', ', $pkeys);
            }
        } else {
            // TODO how to get results from multistatement queries?    
        }
        
        $result = @pg_query($conn, $sql);

        if ($result === false) {
            throw new DataQueryFailedError($query, 'PostgreSQL query failed: ' . pg_last_error($conn));
        }

        $this->affectedRows = pg_affected_rows($result);
        $query->setResultResource($result);

        return $query;
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
}