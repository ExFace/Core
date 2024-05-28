# Setting up an SQL installer step-by-step

## 1. Clean up your dev-database

## 2. Create an install-folder for your SQL scripts

## 3. Export the DDLs for the tables

## 4. Save the DDL for all views

## 5. Register the SQL installer in the PHP class of the app

```
<?php
namespace powerui\PROtrack;

use exface\Core\Interfaces\InstallerInterface;
use exface\Core\CommonLogic\Model\App;
use exface\Core\CommonLogic\AppInstallers\MsSqlDatabaseInstaller;
use exface\Core\CommonLogic\AppInstallers\PWAInstaller;
use axenox\ETL\Common\DataFlowInstaller;
use exface\Core\CommonLogic\AppInstallers\DataInstaller;
use exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller;

class PROtrackApp extends App
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\App::getInstaller()
     */
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $container = parent::getInstaller($injected_installer);
        
        $sqlInstaller = new MySqlDatabaseInstaller($this->getSelector());
        $sqlInstaller
            ->setFoldersWithMigrations(['InitDB','Migrations'])
            ->setFoldersWithStaticSql(['Views'])
            ->setDataSourceSelector('0x11ee8f040d2aeddc8f04025041000001');
        $container->addInstaller($sqlInstaller);
        
        return $container;
    }
}
?>
```

## 6. Add a meta object and a admin page for SQL migrations

