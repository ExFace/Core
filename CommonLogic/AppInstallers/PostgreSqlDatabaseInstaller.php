<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\DataConnectors\PostgreSqlConnector;
use exface\Core\Interfaces\SqlSchemaComparatorInterface;

/**
 * Database installer for PostgreSQL databases.
 * 
 * See AbstractDatabaseInstaller for detailed documentation.
 * 
 * @author Gizem Bicer, Andrej Kabachnik
 */
class PostgreSqlDatabaseInstaller extends MySqlDatabaseInstaller
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::getSqlDbType()
     */
    protected function getSqlDbType(): string
    {
        return 'PostgreSQL';
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::installDatabase()
     */
    protected function installDatabase(SqlDataConnectorInterface $connection, string $indent = ''): \Iterator
    {
        try {
            $connection->connect();
        } catch (DataConnectionFailedError $e) {
            $dbName = $connection->getDatabase();
            $connection->setDatabase('');
            $connection->connect();
            $connection->runSql("CREATE DATABASE {$dbName}");
            $connection->disconnect();
            $connection->setDatabase($dbName);
            $msg = 'Database ' . $dbName . ' created! ';
        }
        yield $indent . $msg . PHP_EOL;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::buildSqlMigrationTableCreate()
     */
    protected function buildSqlMigrationTableCreate(): string
    {
        return <<<SQL
CREATE TABLE IF NOT EXISTS "{$this->getMigrationsTableName()}" (
    id SERIAL PRIMARY KEY,
    migration_name VARCHAR(300) NOT NULL,
    up_datetime TIMESTAMP NOT NULL,
    up_script TEXT NOT NULL,
    up_result TEXT NULL,
    down_datetime TIMESTAMP NULL,
    down_script TEXT NOT NULL,
    down_result TEXT NULL,
    failed_flag SMALLINT NOT NULL DEFAULT 0,
    failed_message TEXT NULL,
    skip_flag SMALLINT NOT NULL DEFAULT 0,
    log_id VARCHAR(10) NULL
);
SQL;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlMigrationUpInsert()
     */
    protected function buildSqlMigrationUpInsert(SqlMigration $migration, string $up_result_string, \DateTime $time) : string
    {
        return rtrim(rtrim(parent::buildSqlMigrationUpInsert($migration, $up_result_string, $time)), ';') . " RETURNING id";
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::getFunctionDropScript()
     */
    protected function getFunctionDropScript(string $funcName): string
    {
        return "DROP FUNCTION IF EXISTS {$funcName}();";
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::checkDataConnection()
     */
    protected function checkDataConnection(SqlDataConnectorInterface $connection): SqlDataConnectorInterface
    {
        if (! ($connection instanceof PostgreSqlConnector)) {
            throw new InstallerRuntimeError($this, 'Only PostgreSqlConnector supported!');
        }
        return $connection;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::canDumpSchema()
     */
    protected function canDumpSchema() : bool
    {
        return true;
    }

    /**
     * Not used in PostgreSQL: the schema dump is built directly in {@see buildSqlSchema()}
     * because PostgreSQL has no single-query equivalent that produces a complete
     * `CREATE TABLE` script for a given table.
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::getTableDumpSchema()
     */
    protected function getTableDumpSchema(string $tableName) : string
    {
        return '';
    }

    /**
     * Builds a normalized, deterministic schema dump for all tables of this installer.
     *
     * The dump intentionally omits all auto-generated names (constraint names, index
     * names, sequence names), because they may differ between database instances. Only
     * the structure (columns, types, nullability, defaults, primary keys, foreign keys
     * and non-PK indexes) is included so that schemas built on different hosts can be
     * compared reliably.
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::buildSqlSchema()
     */
    protected function buildSqlSchema() : string
    {
        $tables = $this->getTables();
        $dump = '';
        foreach ($tables as $table) {
            $dump .= $this->buildSqlSchemaForTable($table['DATA_ADDRESS']);
        }
        $this->getDataConnection()->disconnect();
        return $dump;
    }

    /**
     * Compares two schema dumps produced by {@see buildSqlSchema()}.
     *
     * Since the dumps are deterministic (sorted, no auto-generated names), a plain
     * line-based comparison is sufficient. The returned array contains one entry per
     * structural difference. An empty array means the schemas are equivalent.
     *
     * @param string $currentSchema
     * @param string $previousSchema
     * @return array
     */
    public function performComparison(string $currentSchema, string $previousSchema) : array
    {
        $compartor = new SqlSchemaComparator();

        return $compartor->compare($currentSchema, $previousSchema);
    }

    /**
     * Builds the dump fragment for a single table including columns, PK, FKs and indexes.
     *
     * @param string $tableAddress
     * @return string
     */
    protected function buildSqlSchemaForTable(string $tableAddress) : string
    {
        list($schema, $table) = $this->splitTableName($tableAddress);
        $connection = $this->getDataConnection();
        $quotedQualified = '"' . $schema . '"."' . $table . '"';
        $schemaEsc = $connection->escapeString($schema);
        $tableEsc = $connection->escapeString($table);

        // Skip tables that do not exist in the database to keep the dump robust
        // when the metamodel references views or external addresses.
        $existsSql = "SELECT 1 AS x FROM information_schema.tables WHERE table_schema = '{$schemaEsc}' AND table_name = '{$tableEsc}' AND table_type = 'BASE TABLE'";
        $existsRows = $connection->runSql($existsSql, true)->getResultArray();
        if (empty($existsRows)) {
            return '';
        }

        $columns = $this->fetchColumns($schemaEsc, $tableEsc);
        if (empty($columns)) {
            return '';
        }
        $pkCols = $this->fetchPrimaryKey($schemaEsc, $tableEsc);
        $fks = $this->fetchForeignKeys($schemaEsc, $tableEsc);
        $indexes = $this->fetchIndexes($schemaEsc, $tableEsc);

        $out = "CREATE TABLE {$quotedQualified} (" . PHP_EOL;
        $colLines = [];
        foreach ($columns as $col) {
            $colLines[] = '    ' . $this->buildColumnDefinition($col);
        }
        if (! empty($pkCols)) {
            $quoted = array_map(function ($c) { return '"' . $c . '"'; }, $pkCols);
            $colLines[] = '    PRIMARY KEY (' . implode(', ', $quoted) . ')';
        }
        $out .= implode(',' . PHP_EOL, $colLines) . PHP_EOL;
        $out .= ');' . PHP_EOL;

        foreach ($fks as $fk) {
            $localCols = array_map(function ($c) { return '"' . $c . '"'; }, $fk['columns']);
            $refCols = array_map(function ($c) { return '"' . $c . '"'; }, $fk['ref_columns']);
            $line = 'ALTER TABLE ' . $quotedQualified
                . ' ADD FOREIGN KEY (' . implode(', ', $localCols) . ')'
                . ' REFERENCES "' . $fk['ref_schema'] . '"."' . $fk['ref_table'] . '" (' . implode(', ', $refCols) . ')';
            if ($fk['on_delete'] !== '') {
                $line .= ' ON DELETE ' . $fk['on_delete'];
            }
            if ($fk['on_update'] !== '') {
                $line .= ' ON UPDATE ' . $fk['on_update'];
            }
            $out .= $line . ';' . PHP_EOL;
        }

        foreach ($indexes as $idx) {
            $cols = array_map(function ($c) { return '"' . $c . '"'; }, $idx['columns']);
            $out .= ($idx['unique'] ? 'CREATE UNIQUE INDEX' : 'CREATE INDEX')
                . ' ON ' . $quotedQualified
                . ' (' . implode(', ', $cols) . ');' . PHP_EOL;
        }

        return $out . PHP_EOL;
    }

    /**
     * Splits a table data address into schema and table parts.
     *
     * @param string $tableAddress
     * @return array [schema, table]
     */
    protected function splitTableName(string $tableAddress) : array
    {
        $clean = str_replace('"', '', $tableAddress);
        if (strpos($clean, '.') !== false) {
            list($schema, $table) = explode('.', $clean, 2);
        } else {
            $schema = 'public';
            $table = $clean;
        }
        return [$schema, $table];
    }

    /**
     * Reads column definitions for the given table ordered by column name.
     *
     * @param string $schemaEsc
     * @param string $tableEsc
     * @return array
     */
    protected function fetchColumns(string $schemaEsc, string $tableEsc) : array
    {
        $sql = "SELECT column_name, data_type, udt_name, character_maximum_length, numeric_precision, numeric_scale, datetime_precision, is_nullable, column_default"
            . " FROM information_schema.columns"
            . " WHERE table_schema = '{$schemaEsc}' AND table_name = '{$tableEsc}'"
            . " ORDER BY column_name";
        return $this->getDataConnection()->runSql($sql, true)->getResultArray();
    }

    /**
     * Reads the primary key column names for the given table in PK ordinal order.
     *
     * @param string $schemaEsc
     * @param string $tableEsc
     * @return string[]
     */
    protected function fetchPrimaryKey(string $schemaEsc, string $tableEsc) : array
    {
        $sql = "SELECT a.attname AS column_name, array_position(i.indkey, a.attnum) AS ord"
            . " FROM pg_index i"
            . " JOIN pg_class c ON c.oid = i.indrelid"
            . " JOIN pg_namespace n ON n.oid = c.relnamespace"
            . " JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(i.indkey)"
            . " WHERE i.indisprimary AND n.nspname = '{$schemaEsc}' AND c.relname = '{$tableEsc}'"
            . " ORDER BY ord";
        $rows = $this->getDataConnection()->runSql($sql, true)->getResultArray();
        $cols = [];
        foreach ($rows as $row) {
            $cols[] = $row['column_name'];
        }
        return $cols;
    }

    /**
     * Reads foreign key definitions for the given table.
     *
     * Foreign keys are grouped by constraint OID so that multi-column keys are kept
     * together, but the constraint name itself is intentionally not exported.
     *
     * @param string $schemaEsc
     * @param string $tableEsc
     * @return array
     */
    protected function fetchForeignKeys(string $schemaEsc, string $tableEsc) : array
    {
        $sql = "SELECT c.oid AS conoid,"
            . " ck.ord AS ord,"
            . " a.attname AS column_from,"
            . " nr.nspname AS ref_schema,"
            . " cr.relname AS ref_table,"
            . " af.attname AS column_to,"
            . " c.confdeltype AS on_delete,"
            . " c.confupdtype AS on_update"
            . " FROM pg_constraint c"
            . " JOIN pg_class cl ON cl.oid = c.conrelid"
            . " JOIN pg_namespace n ON n.oid = cl.relnamespace"
            . " JOIN pg_class cr ON cr.oid = c.confrelid"
            . " JOIN pg_namespace nr ON nr.oid = cr.relnamespace"
            . " JOIN unnest(c.conkey) WITH ORDINALITY AS ck(attnum, ord) ON true"
            . " JOIN unnest(c.confkey) WITH ORDINALITY AS fk(attnum, ord) ON fk.ord = ck.ord"
            . " JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ck.attnum"
            . " JOIN pg_attribute af ON af.attrelid = c.confrelid AND af.attnum = fk.attnum"
            . " WHERE c.contype = 'f' AND n.nspname = '{$schemaEsc}' AND cl.relname = '{$tableEsc}'"
            . " ORDER BY column_from, ord";
        $rows = $this->getDataConnection()->runSql($sql, true)->getResultArray();
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['conoid'];
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'columns' => [],
                    'ref_columns' => [],
                    'ref_schema' => $row['ref_schema'],
                    'ref_table' => $row['ref_table'],
                    'on_delete' => $this->mapFkAction($row['on_delete']),
                    'on_update' => $this->mapFkAction($row['on_update']),
                ];
            }
            $grouped[$key]['columns'][] = $row['column_from'];
            $grouped[$key]['ref_columns'][] = $row['column_to'];
        }
        // Sort deterministically by serialized signature.
        usort($grouped, function ($a, $b) {
            return strcmp(
                implode(',', $a['columns']) . '->' . $a['ref_schema'] . '.' . $a['ref_table'] . '(' . implode(',', $a['ref_columns']) . ')',
                implode(',', $b['columns']) . '->' . $b['ref_schema'] . '.' . $b['ref_table'] . '(' . implode(',', $b['ref_columns']) . ')'
            );
        });
        return $grouped;
    }

    /**
     * Reads non-PK, non-unique-constraint indexes for the given table.
     *
     * Index names are not exported, only the column list and uniqueness flag.
     *
     * @param string $schemaEsc
     * @param string $tableEsc
     * @return array
     */
    protected function fetchIndexes(string $schemaEsc, string $tableEsc) : array
    {
        $sql = "SELECT i.indexrelid AS idxoid,"
            . " a.attname AS column_name,"
            . " array_position(i.indkey, a.attnum) AS ord,"
            . " i.indisunique AS is_unique"
            . " FROM pg_index i"
            . " JOIN pg_class c ON c.oid = i.indrelid"
            . " JOIN pg_namespace n ON n.oid = c.relnamespace"
            . " JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(i.indkey)"
            . " WHERE n.nspname = '{$schemaEsc}' AND c.relname = '{$tableEsc}'"
            . " AND i.indisprimary = false"
            . " AND NOT EXISTS ("
            . "   SELECT 1 FROM pg_constraint cc"
            . "   WHERE cc.conindid = i.indexrelid AND cc.contype IN ('u','p')"
            . " )"
            . " ORDER BY idxoid, ord";
        $rows = $this->getDataConnection()->runSql($sql, true)->getResultArray();
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['idxoid'];
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'columns' => [],
                    'unique' => $this->toBool($row['is_unique']),
                ];
            }
            $grouped[$key]['columns'][] = $row['column_name'];
        }
        usort($grouped, function ($a, $b) {
            return strcmp(
                ($a['unique'] ? 'U:' : 'I:') . implode(',', $a['columns']),
                ($b['unique'] ? 'U:' : 'I:') . implode(',', $b['columns'])
            );
        });
        return $grouped;
    }

    /**
     * Renders the column definition portion of a `CREATE TABLE` statement.
     *
     * Auto-increment serials are normalized to their underlying integer type so that
     * the dump does not depend on sequence object identity.
     *
     * @param array $col
     * @return string
     */
    protected function buildColumnDefinition(array $col) : string
    {
        $line = '"' . $col['column_name'] . '" ' . $this->buildColumnType($col);
        if (strcasecmp($col['is_nullable'], 'NO') === 0) {
            $line .= ' NOT NULL';
        }
        $default = $col['column_default'];
        if ($default !== null && $default !== '') {
            // Strip references to auto-generated sequences so dumps are portable.
            if (preg_match('/^nextval\(/i', $default)) {
                // Skip serial defaults: they belong to identity columns and would
                // otherwise embed schema-specific sequence names.
            } else {
                $line .= ' DEFAULT ' . $default;
            }
        }
        return $line;
    }

    /**
     * Builds the SQL type expression for a column row from `information_schema.columns`.
     *
     * @param array $col
     * @return string
     */
    protected function buildColumnType(array $col) : string
    {
        $type = strtolower($col['data_type']);
        switch ($type) {
            case 'character varying':
            case 'character':
                if ($col['character_maximum_length'] !== null && $col['character_maximum_length'] !== '') {
                    return $type . '(' . $col['character_maximum_length'] . ')';
                }
                return $type;
            case 'numeric':
            case 'decimal':
                if ($col['numeric_precision'] !== null && $col['numeric_precision'] !== '') {
                    $scale = ($col['numeric_scale'] !== null && $col['numeric_scale'] !== '') ? $col['numeric_scale'] : '0';
                    return $type . '(' . $col['numeric_precision'] . ',' . $scale . ')';
                }
                return $type;
            case 'time':
            case 'time without time zone':
            case 'time with time zone':
            case 'timestamp':
            case 'timestamp without time zone':
            case 'timestamp with time zone':
                if ($col['datetime_precision'] !== null && $col['datetime_precision'] !== '') {
                    // Insert precision before any "with/without time zone" suffix.
                    if (strpos($type, ' ') !== false) {
                        $base = substr($type, 0, strpos($type, ' '));
                        $suffix = substr($type, strpos($type, ' '));
                        return $base . '(' . $col['datetime_precision'] . ')' . $suffix;
                    }
                    return $type . '(' . $col['datetime_precision'] . ')';
                }
                return $type;
            case 'user-defined':
                return $col['udt_name'];
            default:
                return $type;
        }
    }

    /**
     * Maps a single-character `pg_constraint` referential action code to its SQL form.
     *
     * @param string $code
     * @return string
     */
    protected function mapFkAction(string $code) : string
    {
        switch ($code) {
            case 'c': return 'CASCADE';
            case 'n': return 'SET NULL';
            case 'd': return 'SET DEFAULT';
            case 'r': return 'RESTRICT';
            case 'a':
            default:
                return '';
        }
    }

    /**
     * Normalizes the various truthy values PostgreSQL drivers may return for booleans.
     *
     * @param mixed $val
     * @return bool
     */
    protected function toBool($val) : bool
    {
        if (is_bool($val)) {
            return $val;
        }
        if (is_numeric($val)) {
            return (int) $val !== 0;
        }
        $s = strtolower((string) $val);
        return $s === 't' || $s === 'true' || $s === 'y' || $s === 'yes';
    }
}