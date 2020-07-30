<?php
namespace exface\Core\ModelLoaders;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\DataConnectors\MsSqlConnector;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\CommonLogic\AppInstallers\AppInstallerContainer;
use exface\Core\CommonLogic\AppInstallers\MsSqlDatabaseInstaller;

/**
 * Loads metamodel entities from a Microsoft SQL Server datatabse.
 * 
 * @author Ralf Mulansky
 *
 */
class MsSqlModelLoader extends SqlModelLoader
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelLoaders\SqlModelLoader::buildSqlUuidSelector()
     */
    protected function buildSqlUuidSelector($field_name) : string
    {
        return "LOWER(CONVERT(VARCHAR(34), {$field_name}, 1))";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelLoaders\SqlModelLoader::buildSqlGroupConcat()
     */
    protected function buildSqlGroupConcat(string $sqlColumn, string $sqlFrom, string $sqlWhere) : string
    {
        return <<<SQL
        
        SELECT STUFF(CAST(( SELECT [text()] = ', ' + {$sqlColumn}
        FROM {$sqlFrom}
        WHERE {$sqlWhere}
        FOR XML PATH(''), TYPE) AS VARCHAR(1000)), 1, 2, '')
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
        if (! ($connection instanceof MsSqlConnector)) {
            throw new \RuntimeException('Incompatible connector "' . $connection->getPrototypeClassName() . '" used for the model loader "' . get_class($this) . '": expecting a MsSqlConnector or a derivative.');
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
            $dbInstaller = new MsSqlDatabaseInstaller($coreAppSelector);
            $dbInstaller
            ->setFoldersWithMigrations(['InitDB','Migrations'])
            ->setFoldersWithStaticSql(['Views'])
            ->setDataConnection($modelConnection);
            
            $installer->addInstaller($dbInstaller);
            $this->installer = $installer;
        }
        return $this->installer;
    }
}