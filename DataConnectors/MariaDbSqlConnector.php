<?php
namespace exface\Core\DataConnectors;

/**
 * Data source connector for MariaDB databases
 * 
 * For server-managed credentials, see [Secrets in environment variables](../Docs/Security/Secure_data_sources/Secrets_in_env_vars.md)
 * with `~env:` and `~system:` configuration examples for Apache, nginx and IIS.
 * 
 * @author Andrej Kabachnik
 */
class MariaDbSqlConnector extends MySqlConnector
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getSqlDialect()
     */
    public function getSqlDialect(): string
    {
        return 'MariaDB';
    }
}