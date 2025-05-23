<?php

namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\DataConnectors\MsSqlConnector;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * Database AppInstaller for Apps with Microsoft SQL Server Database.
 * 
 * ## Encoding
 * 
 * This installer currently requires SQL files to be encoded as UTF8!!!
 * 
 * ## Transaction handling
 * 
 * NOTE: SQL Server seems to only support DDL rollbacks in certain stuations,
 * so (similarly to MySQL) we wrap each UP/DOWN script in a transaction - this
 * ensures, that if a script was performed successfully, all it's changes
 * are committed - DDL and DML. If not done so, most changes might get rolled
 * back if something in the next migration script goes wrong, while certain DDL
 * changes would remain due to their implicit commit.
 *
 * @author Ralf Mulansky
 *
 */
class MsSqlDatabaseInstaller extends MySqlDatabaseInstaller
{  
    private $sql_migrations_prefix = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::getSqlDbType()
     */
    protected function getSqlDbType() : string
    {
        return 'MsSQL';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::getBatchDelimiter()
     */
    protected function getBatchDelimiter(string $sql) : ?string
    {
        return parent::getBatchDelimiter($sql) ?? '/^GO;?/m';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::installDatabase()
     */
    protected function installDatabase(SqlDataConnectorInterface $connection, string $indent = '') : \Iterator
    {        
        $msg = '';
        try {
            $connection->connect();
        } catch (DataConnectionFailedError $e) {            
            $dbName = $connection->getDatabase();
            $connection->setDatabase('');
            $connection->connect();
            $database_create = "CREATE DATABASE {$dbName}";
            $connection->runSql($database_create);
            $database_use = "USE {$dbName};";
            $connection->runSql($database_use);
            $connection->disconnect();
            $connection->setDatabase($dbName);
            $msg = 'Database ' . $dbName . ' created! ';
        }
        yield $indent . $msg . PHP_EOL;
    }    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlCreateMigrationTable()
     */
    protected function buildSqlMigrationTableCreate() : string
    {
        $pkName = 'PK_' . parent::getMigrationsTableName() . '_id';
        // in case any changes need to be made to the migrations table, make the changes in the CREATE TABLE statement
        // also add the changes as a seperate statement (like the ones below the CREATE TABLE statement) so that
        // already existing installations will be updated
        return <<<SQL
-- creation of migrations table
IF OBJECT_ID('{$this->getMigrationsTableName()}', 'U') IS NULL  
BEGIN       
    CREATE TABLE {$this->getMigrationsTableName()}(
    	[id] [int] IDENTITY(40,1) NOT NULL,
    	[migration_name] [nvarchar](300) NOT NULL,
    	[up_datetime] [datetime] NOT NULL,
    	[up_script] [nvarchar](max) NOT NULL,
    	[up_result] [nvarchar](max) NULL,
    	[down_datetime] [datetime] NULL,
    	[down_script] [nvarchar](max) NOT NULL,
    	[down_result] [nvarchar](max) NULL,
        [failed_flag] tinyint NOT NULL DEFAULT 0,
        [failed_message] [nvarchar](max) NULL,
        [skip_flag] tinyint NOT NULL DEFAULT 0,
        [log_id] varchar(10) NULL,
        CONSTRAINT [{$pkName}] PRIMARY KEY CLUSTERED 
        (
    	   [id] ASC
        )
        WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
    ) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
END;

-- update to add `failed_flag`, `failed_message` and `skip_flag` columns
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'{$this->getMigrationsTableName()}') AND name LIKE '%failed%')
BEGIN
    ALTER TABLE {$this->getMigrationsTableName()} ADD
        [failed_flag] tinyint NOT NULL DEFAULT 0,
        [failed_message] [nvarchar](max) NULL,
        [skip_flag] tinyint NOT NULL DEFAULT 0
    ALTER TABLE {$this->getMigrationsTableName()} ALTER COLUMN [up_result] [nvarchar](max) NULL
END;

-- update to add `log_id` column
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'{$this->getMigrationsTableName()}') AND name LIKE '%log_id%')
BEGIN
    ALTER TABLE {$this->getMigrationsTableName()} ADD [log_id] varchar(10) NULL
END;

SQL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlMigrationUpInsert()
     */
    protected function buildSqlMigrationUpInsert(SqlMigration $migration, string $up_result_string, \DateTime $time) : string
    {
        return parent::buildSqlMigrationUpInsert($migration, $up_result_string, $time) . " SELECT SCOPE_IDENTITY();";
    }    
    
    /**
     * Set the prefix of the SQL table to store the migration log.
     *
     * @return string
     */
    public function setMigrationsTablePrefix(string $prefix) : AbstractSqlDatabaseInstaller
    {
        $this->sql_migrations_prefix = $prefix;
        return $this;
    }
    
    /**
     * Returns the prefix of the SQL table to store the migration log.
     *
     * @return string
     */
    protected function getMigrationsTablePrefix() : ?string
    {
        if ($this->sql_migrations_prefix) {
            return $this->sql_migrations_prefix;
        }
        return 'dbo';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::getMigrationsTableName()
     */
    public function getMigrationsTableName() : string
    {
        return "[" . $this->getMigrationsTablePrefix() . "].[" . parent::getMigrationsTableName() . "]";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::escapeSqlStringValue()
     */
    protected function escapeSqlStringValue(string $value) : string
    {
        return str_replace("'", "''", $value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\AbstractSqlDatabaseInstaller::checkDataConnection()
     */
    protected function checkDataConnection(SqlDataConnectorInterface $connection) : SqlDataConnectorInterface
    {
        if (! $connection instanceof MsSqlConnector) {
            throw new InstallerRuntimeError($this, 'Cannot use connection "' . $connection->getAliasWithNamespace() . '" with Microsoft SQL Server DB installer: only instances of "MsSqlConnector" supported!');
        }
        return $connection;
    }
    
    /**
     *
     * @param \DateTime $time
     * @return string
     */
    protected function escapeSqlDateTimeValue(\DateTime $time) : string
    {
        // There have been issues with dates when logging migration success/failure on different SQL Server instances
        // This option only seems to work if SQL Server language is "en"
        // return "CAST('" . DateTimeDataType::formatDateNormalized($time) . "' AS DATETIME)";
        // This statementa convert a string formatted as `yyyy-mm-dd hh:mm:ss:nnn` to datetime. 
        // The format number `120` corresponds to the ODBC canonical format without milliseconds (whereas
        // `121` would be with milliseconds).
        // @link https://learn.microsoft.com/en-us/sql/t-sql/functions/cast-and-convert-transact-sql?view=sql-server-ver16
        return "CONVERT(datetime, '" . DateTimeDataType::formatDateNormalized($time) . "', 120)";
        // This is what the MsSqlBuilder seems to use anyhow
        // return "'" . DateTimeDataType::formatDateNormalized($time) . "'";
    }

    protected function getTableDumpSchema(string $tableName) : string
    {
        $tableNameEscaped = str_replace("'", "''", $tableName); 
        return <<<SQL
        DECLARE @raw_table_name NVARCHAR(MAX) = N'$tableNameEscaped';
DECLARE @schema_name SYSNAME;
DECLARE @table_name SYSNAME;
DECLARE @object_name SYSNAME;
DECLARE @object_id INT;

IF CHARINDEX('.', @raw_table_name) > 0
BEGIN
    SET @schema_name = PARSENAME(@raw_table_name, 2);
    SET @table_name = PARSENAME(@raw_table_name, 1);
END
ELSE
BEGIN
    SET @schema_name = SCHEMA_NAME();
    SET @table_name = @raw_table_name;
END

SET @object_name = QUOTENAME(@schema_name) + '.' + QUOTENAME(@table_name);

SELECT @object_id = OBJECT_ID(@object_name);

DECLARE @SQL NVARCHAR(MAX) = '';

WITH index_column AS (
    SELECT 
      ic.[object_id], 
      ic.index_id, 
      ic.is_descending_key, 
      ic.is_included_column, 
      c.name
    FROM 
      sys.index_columns ic WITH (NOLOCK)
      JOIN sys.columns c WITH (NOLOCK) ON ic.[object_id] = c.[object_id] 
      AND ic.column_id = c.column_id
    WHERE 
      ic.[object_id] = @object_id
),
fk_columns AS (
    SELECT 
      k.constraint_object_id, 
      c.name AS cname, 
      rc.name AS rcname
    FROM 
      sys.foreign_key_columns k WITH (NOLOCK)
      JOIN sys.columns rc WITH (NOLOCK) ON rc.[object_id] = k.referenced_object_id 
      AND rc.column_id = k.referenced_column_id
      JOIN sys.columns c WITH (NOLOCK) ON c.[object_id] = k.parent_object_id 
      AND c.column_id = k.parent_column_id
    WHERE 
      k.parent_object_id = @object_id
)

SELECT @SQL = 'CREATE TABLE ' + @object_name + CHAR(13) + '(' + CHAR(13) + 
STUFF((
    SELECT CHAR(9) + ', [' + c.name + '] ' +
      CASE 
        WHEN c.is_computed = 1 THEN 'AS ' + cc.definition
        ELSE UPPER(tp.name) +
          CASE 
            WHEN tp.name IN ('varchar', 'char', 'varbinary', 'binary', 'text') THEN 
              '(' + CASE WHEN c.max_length = -1 THEN 'MAX' ELSE CAST(c.max_length AS VARCHAR(5)) END + ')'
            WHEN tp.name IN ('nvarchar', 'nchar', 'ntext') THEN 
              '(' + CASE WHEN c.max_length = -1 THEN 'MAX' ELSE CAST(CAST(c.max_length AS FLOAT) / 2 AS VARCHAR(5)) END + ')'
            WHEN tp.name IN ('datetime2', 'time2', 'datetimeoffset') THEN 
              '(' + CAST(c.scale AS VARCHAR(5)) + ')'
            WHEN tp.name IN ('decimal', 'numeric') THEN 
              '(' + CAST(c.[precision] AS VARCHAR(5)) + ',' + CAST(c.scale AS VARCHAR(5)) + ')'
            ELSE ''
          END +
          CASE WHEN c.collation_name IS NOT NULL THEN ' COLLATE ' + c.collation_name ELSE '' END +
          CASE WHEN c.is_nullable = 1 THEN ' NULL' ELSE ' NOT NULL' END +
          CASE WHEN dc.definition IS NOT NULL THEN ' DEFAULT' + dc.definition ELSE '' END +
          CASE WHEN ic.is_identity = 1 THEN ' IDENTITY(' + 
            CAST(ISNULL(ic.seed_value, 0) AS VARCHAR(20)) + ',' + 
            CAST(ISNULL(ic.increment_value,1) AS VARCHAR(20)) + ')' ELSE '' 
          END
      END + CHAR(13)
    FROM 
      sys.columns c WITH (NOLOCK) 
      JOIN sys.types tp WITH (NOLOCK) ON c.user_type_id = tp.user_type_id
      LEFT JOIN sys.computed_columns cc WITH (NOLOCK) ON c.[object_id] = cc.[object_id] AND c.column_id = cc.column_id
      LEFT JOIN sys.default_constraints dc WITH (NOLOCK) ON c.default_object_id = dc.[object_id]
      LEFT JOIN sys.identity_columns ic WITH (NOLOCK) ON c.[object_id] = ic.[object_id] AND c.column_id = ic.column_id
    WHERE 
      c.[object_id] = @object_id
    ORDER BY 
      c.name
    FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)')
,1,2,CHAR(9) + ' ') + 
ISNULL((
    SELECT CHAR(9) + ', CONSTRAINT [' + k.name + '] PRIMARY KEY (' +
      STUFF((
        SELECT ', [' + c.name + '] ' + CASE WHEN ic.is_descending_key = 1 THEN 'DESC' ELSE 'ASC' END
        FROM sys.index_columns ic WITH (NOLOCK)
        JOIN sys.columns c WITH (NOLOCK) ON c.[object_id] = ic.[object_id] AND c.column_id = ic.column_id
        WHERE 
          ic.is_included_column = 0 
          AND ic.[object_id] = k.parent_object_id 
          AND ic.index_id = k.unique_index_id
        FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)')
      ,1,2,'') +
    ')' + CHAR(13)
    FROM sys.key_constraints k WITH (NOLOCK)
    WHERE 
      k.parent_object_id = @object_id 
      AND k.[type] = 'PK'
), '') +
')' + CHAR(13) +

ISNULL((
    SELECT (
      SELECT 
        CHAR(13) + 'ALTER TABLE ' + @object_name + ' WITH' +
          CASE WHEN fk.is_not_trusted = 1 THEN ' NOCHECK' ELSE ' CHECK' END +
          ' ADD CONSTRAINT [' + fk.name + '] FOREIGN KEY (' +
          STUFF((
            SELECT ', [' + k.cname + ']'
            FROM fk_columns k
            WHERE k.constraint_object_id = fk.[object_id]
            FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)')
          ,1,2,'') + ')' +
          ' REFERENCES [' + SCHEMA_NAME(ro.schema_id) + '].[' + ro.name + '] (' +
          STUFF((
            SELECT ', [' + k.rcname + ']'
            FROM fk_columns k
            WHERE k.constraint_object_id = fk.[object_id]
            FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)')
          ,1,2,'') + ')' +
          CASE WHEN fk.delete_referential_action = 1 THEN ' ON DELETE CASCADE'
               WHEN fk.delete_referential_action = 2 THEN ' ON DELETE SET NULL'
               WHEN fk.delete_referential_action = 3 THEN ' ON DELETE SET DEFAULT'
               ELSE ''
          END +
          CASE WHEN fk.update_referential_action = 1 THEN ' ON UPDATE CASCADE'
               WHEN fk.update_referential_action = 2 THEN ' ON UPDATE SET NULL'
               WHEN fk.update_referential_action = 3 THEN ' ON UPDATE SET DEFAULT'
               ELSE ''
          END + CHAR(13) +
          'ALTER TABLE ' + @object_name + ' CHECK CONSTRAINT [' + fk.name + ']' + CHAR(13)
      FROM 
        sys.foreign_keys fk WITH (NOLOCK)
        JOIN sys.objects ro WITH (NOLOCK) ON ro.object_id = fk.referenced_object_id
      WHERE 
        fk.parent_object_id = @object_id
      FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)')
), '') +

ISNULL((
    SELECT (
      SELECT 
        CHAR(13) + 'CREATE' +
          CASE WHEN i.is_unique = 1 THEN ' UNIQUE' ELSE '' END +
          ' NONCLUSTERED INDEX [' + i.name + '] ON ' + @object_name + ' (' +
          STUFF((
            SELECT ', [' + c.name + '] ' + CASE WHEN c.is_descending_key = 1 THEN 'DESC' ELSE 'ASC' END
            FROM index_column c
            WHERE c.is_included_column = 0 
              AND c.index_id = i.index_id
            FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)')
          ,1,2,'') + ')' +
          ISNULL(
            CHAR(13) + 'INCLUDE (' +
            STUFF((
              SELECT ', [' + c.name + ']'
              FROM index_column c
              WHERE c.is_included_column = 1 
                AND c.index_id = i.index_id
              FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)')
            ,1,2,'') + ')'
          , '') + CHAR(13)
      FROM 
        sys.indexes i WITH (NOLOCK)
      WHERE 
        i.[object_id] = @object_id 
        AND i.is_primary_key = 0 
        AND i.[type] = 2
      FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)')
), '')

SELECT @SQL AS CreateTableScript;


SQL;

    }
    
    
    function extractCreateTableBlocks($sql) 
    {
      $blocks = [];
      $offset = 0;
  
      while (($start = stripos($sql, 'CREATE TABLE', $offset)) !== false) {
          $openParen = strpos($sql, '(', $start);
          if ($openParen === false) break;
  
          $depth = 1;
          $i = $openParen + 1;
  
          while ($depth > 0 && $i < strlen($sql)) {
              if ($sql[$i] === '(') $depth++;
              elseif ($sql[$i] === ')') $depth--;
              $i++;
          }
  
          $block = substr($sql, $start, $i - $start);
          $blocks[] = $block;
  
          $offset = $i;
        }
    
        return $blocks;
    }
    
    function parseTablesAndPrimaryKeys(string $sql) 
    {
      $tables = [];
      
      $blocks = $this->extractCreateTableBlocks($sql);
      foreach ($blocks as $block) {
          if (preg_match('/CREATE TABLE\s+\[dbo\]\.\[(\w+)\]/i', $block, $match)) {
              $tableName = $match[1];

              $columns = [];
              $primaryKey = [];

              $lines = preg_split('/,\r?\n|\r|\n/', $block);

              foreach ($lines as $line) {
                  $line = trim($line, " \t\n\r\0\x0B,");
                  // PRIMARY KEY
                  if (preg_match('/CONSTRAINT\s+\[.*?\]\s+PRIMARY KEY\s*\((.*?)\)/i', $line, $pkMatch)) {
                      preg_match_all('/\[(\w+)\]/', $pkMatch[1], $pkCols);
                      $primaryKey = $pkCols[1];
                      continue;
                  }

                  if (preg_match('/^\[([^\]]+)\]\s+([A-Z0-9\(\)]+)(?:\s+COLLATE\s+[^\s]+)?(?:\s+(NOT\s+NULL|NULL))?/i', $line, $colMatch)) {
                      $columns[] = [
                          'name' => $colMatch[1],
                          'type' => strtoupper($colMatch[2]),
                          'nullable' => !isset($colMatch[3]) || strtoupper($colMatch[3]) !== 'NOT NULL'
                      ];
                  }
              }

              $tables[$tableName] = [
                  'columns' => $columns,
                  'primary_key' => $primaryKey
              ];
          }
      }

      return $tables;
    }
  
      function parseForeignKeys(string $sql) {
        $foreignKeys = [];

        preg_match_all(
            '/ALTER TABLE \[dbo\]\.\[(\w+)\] WITH CHECK ADD CONSTRAINT \[[^\]]+\] FOREIGN KEY \(\[(\w+)\]\) REFERENCES \[dbo\]\.\[(\w+)\] \(\[(\w+)\]\)/i',
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $table = $match[1];
            $column = $match[2];
            $referencedTable = $match[3];
            $referencedColumn = $match[4];

            if (!isset($foreignKeys[$table])) {
                $foreignKeys[$table] = [];
            }

            $foreignKeys[$table][] = [
                'column' => $column,
                'references_table' => $referencedTable,
                'references_column' => $referencedColumn
            ];
        }

        return $foreignKeys;
    }

    function compareSchemasDetailed(array $currentSchema, array $previousSchema) 
    {
        $differences = [];
    
        $currentTables = array_keys($currentSchema['tables']);
        $previousTables = array_keys($previousSchema['tables']);
    
        $missingInPrevious = array_diff($currentTables, $previousTables);
        $missingInCurrent = array_diff($previousTables, $currentTables);
    
        foreach ($missingInPrevious as $table) {
            $differences[] = "Table '$table' is missing in previous schema";
        }
        foreach ($missingInCurrent as $table) {
            $differences[] = "Table '$table' is missing in current schema";
        }
    
        // check common tables
        $commonTables = array_intersect($currentTables, $previousTables);
        foreach ($commonTables as $table) {
            // compare columns
            $currentSchemaColumns = $currentSchema['tables'][$table]['columns'];
            $previousSchemaColumns = $previousSchema['tables'][$table]['columns'];
    
            $colNamesCurrent = array_column($currentSchemaColumns, 'name');
            $colNamesPrevious = array_column($previousSchemaColumns, 'name');
    
            $missingColsInPrevious = array_diff($colNamesCurrent, $colNamesPrevious);
            $missingColsInCurrent = array_diff($colNamesPrevious, $colNamesCurrent);
    
            foreach ($missingColsInPrevious as $col) {
                $differences[] = "Column '$col' is missing in table '$table' in previous schema";
            }
            foreach ($missingColsInCurrent as $col) {
                $differences[] = "Column '$col' is missing in table '$table' in current schema";
            }
    
            // compare Primary key columns
            $pkCurrent = $currentSchema['tables'][$table]['primary_key'];
            $pkPrevious = $previousSchema['tables'][$table]['primary_key'];
    
            if ($pkCurrent !== $pkPrevious) {
                $differences[] = "Primary key mismatch in table '$table': [" . implode(', ', $pkCurrent) . "] vs [" . implode(', ', $pkPrevious) . "]";
            }
    
            // compare Foreign keys with table and columns 
            $fkCurrent = $currentSchema['foreign_keys'][$table] ?? [];
            $fkPrevious = $previousSchema['foreign_keys'][$table] ?? [];
    
            $normalizedFk1 = array_map(function($fk) {
                return "{$fk['column']}->{$fk['references_table']}.{$fk['references_column']}";
            }, $fkCurrent);
            $normalizedFk2 = array_map(function($fk) {
                return "{$fk['column']}->{$fk['references_table']}.{$fk['references_column']}";
            }, $fkPrevious);
    
            $missingFkInPrevious = array_diff($normalizedFk1, $normalizedFk2);
            $missingFkInCurrent = array_diff($normalizedFk2, $normalizedFk1);
    
            foreach ($missingFkInPrevious as $fk) {
                $differences[] = "Foreign key '$fk' missing in previous schema table '$table'";
            }
            foreach ($missingFkInCurrent as $fk) {
                $differences[] = "Foreign key '$fk' missing in current schema table '$table'";
            }
        }
    
        return $differences;
    }
    
    function performComparison(string $currentSchema, string $previousSchema) : array
    {
      $schema1 = [
        'tables' => $this->parseTablesAndPrimaryKeys($currentSchema),
        'foreign_keys' => $this->parseForeignKeys($currentSchema),
      ];
      
      $schema2 = [
          'tables' => $this->parseTablesAndPrimaryKeys($previousSchema),
          'foreign_keys' => $this->parseForeignKeys($previousSchema),
      ];
      
      return $this->compareSchemasDetailed($schema1, $schema2);
    }

}