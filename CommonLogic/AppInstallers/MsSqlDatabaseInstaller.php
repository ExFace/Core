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
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlShowMigrationTable()
     */
    protected function buildSqlMigrationTableShow() : string
    {
        return <<<SQL

IF OBJECT_ID('{$this->getMigrationsTableName()}', 'U') IS NOT NULL
BEGIN
 SELECT OBJECT_ID('{$this->getMigrationsTableName()}', 'U') AS id
END

SQL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlCreateMigrationTable()
     */
    protected function buildSqlMigrationTableCreate() : string
    {
        $pkName = 'PK_' . parent::getMigrationsTableName() . '_id';
        return <<<SQL
        
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
    [skip_flag] tinyint NOT NULL DEFAULT 0    
    CONSTRAINT [{$pkName}] PRIMARY KEY CLUSTERED 
    (
	   [id] ASC
    )
    WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY];

SQL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlMigrationTableAtler()
     */
    protected function buildSqlMigrationTableAtler() : string
    {
        //no check if columns exists, if so probably will give an error
        return <<<SQL
        
ALTER TABLE {$this->getMigrationsTableName()} ADD
    [failed_flag] tinyint NOT NULL DEFAULT 0,
    [failed_message] [nvarchar](max) NULL,
    [skip_flag] tinyint NOT NULL DEFAULT 0;
ALTER TABLE {$this->getMigrationsTableName()} ALTER COLUMN [up_result] [nvarchar](max) NULL;

SQL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlShowColumnFailed()
     */
    protected function buildSqlShowColumnFailed() : string
    {
        return <<<SQL
        
SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'{$this->getMigrationsTableName()}') AND name LIKE '%failed%';

SQL;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller::buildSqlMigrationUpInsert()
     */
    protected function buildSqlMigrationUpInsert(SqlMigration $migration, string $up_result_string) : string
    {
        return parent::buildSqlMigrationUpInsert($migration, $up_result_string) . "SELECT SCOPE_IDENTITY();";
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
        return "CAST('" . DateTimeDataType::formatDateNormalized($time) . "' AS DATETIME)";
    }
}