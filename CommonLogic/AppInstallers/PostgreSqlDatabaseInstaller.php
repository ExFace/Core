<?php
namespace exface\Core\CommonLogic\AppInstallers;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\DataConnectors\PostgreSqlConnector;

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
    failed_flag BOOLEAN NOT NULL DEFAULT FALSE,
    failed_message TEXT NULL,
    skip_flag BOOLEAN NOT NULL DEFAULT FALSE,
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
}