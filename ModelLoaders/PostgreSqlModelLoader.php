<?php
namespace exface\Core\ModelLoaders;

use exface\Core\CommonLogic\AppInstallers\PostgreSqlDatabaseInstaller;
use exface\Core\DataConnectors\PostgreSqlConnector;
use exface\Core\Interfaces\AppInstallerInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\CommonLogic\AppInstallers\AppInstallerContainer;

/**
 * Loads metamodel entities from a Microsoft SQL Server datatabse.
 * 
 * @author Ralf Mulansky
 *
 */
class PostgreSqlModelLoader extends SqlModelLoader
{
    private AppInstallerInterface|null $installer = null;
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelLoaders\SqlModelLoader::buildSqlUuidSelector()
     */
    protected function buildSqlUuidSelector($field_name) : string
    {
        // In PostgreSQL casting to `::text` should normalize all letters to lowercase
        return "CONCAT('0x', REPLACE({$field_name}::text, '-', ''))";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelLoaders\SqlModelLoader::buildSqlGroupConcat()
     */
    protected function buildSqlGroupConcat(string $sqlColumn, string $sqlFrom, string $sqlWhere, string $delimiter = ',') : string
    {
        return <<<SQL
        
        SELECT string_agg({$sqlColumn}, '{$delimiter}')
        FROM {$sqlFrom}
        WHERE {$sqlWhere}
SQL;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\ModelLoaderInterface::setDataConnection()
     */
    public function setDataConnection(DataConnectionInterface $connection)
    {
        if (! ($connection instanceof PostgreSqlConnector)) {
            throw new \RuntimeException('Incompatible connector "' . $connection->getPrototypeClassName() . '" used for the model loader "' . get_class($this) . '": expecting a PostgreSqlConnector or a derivative.');
        }
        return parent::setDataConnection($connection);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelLoaders\SqlModelLoader::getInstaller()
     */
    public function getInstaller()
    {
        if ($this->installer === null) {
            $coreAppSelector = new AppSelector($this->getDataConnection()->getWorkbench(), 'exface.Core');
            $installer = new AppInstallerContainer($coreAppSelector);
            
            // Init the SQL installer
            $modelConnection = $this->getDataConnection();
            $dbInstaller = new PostgreSqlDatabaseInstaller($coreAppSelector);
            $dbInstaller
                ->setFoldersWithFunctions(['Functions'])
                ->setFoldersWithMigrations(['InitDB','Migrations'])
                ->setFoldersWithStaticSql(['Views'])
                ->setDataConnection($modelConnection);
            
            $installer->addInstaller($dbInstaller);
            $this->installer = $installer;
        }
        return $this->installer;
    }
}