# Installing apps with AppInstallers

Every app class provides an installer (via `AppInterface::getInstaller()`) that takes care of initializing or removing non-PHP dependencies. This installer is called automatically once the app had been downloaded via PHP composer or if it is installed manually via Administration > Metamodel.

Installers are PHP classes, that implement the `\exface\Core\Interfaces\AppInstallerInterface`. In a nutshell, this interface makes the class behave similarly to a Windows installer executable by providing methods to install and uninstall an app. Additionally AppInstallers support a `backup()` method.

The core includes a couple of installer classes for common purposes like maintaining SQL databases, registering facades, etc. These installers can be easily used in every app. On the other hand, apps may provide custom and even reusable installers of their own.

## Adding an Installer to an app

To add a custom installer to an app create a PHP file in the app main folder, named like the app without spaces and add 'App' to it. For example for an App named `Demo MES` the PHP installation needs to be named `DemoMESApp.php`.
This PHP file contains the class named like the file which extends the class `App`. In this class override the function `public function getInstaller()` from the `App` class.

To include the base installer in the App call the function `$installer = parent::getInstaller($injected_installer);`.

To add an installer to an app create a new object of the desired installer, for example like: `$schema_installer = new MySqlDatabaseInstaller($this->getSelector())`.
Configure the new installer and then add it to the AppInstaller like
`$installer->addInstaller($schema_installer);`

It is possible to add multiple installers like that to the AppInstaller.

Example for a custom installer for an App named `Demo MES`:

	<?php
		
	use exface\Core\Interfaces\InstallerInterface;
	use exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller;
	use exface\Core\CommonLogic\Model\App;
	use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
	use exface\Core\Factories\DataSourceFactory;
	
	class DemoMESApp extends App
	{
	    public function getInstaller(InstallerInterface $injected_installer = null)
	    {
	        $installer = parent::getInstaller($injected_installer);        
	        $dataSource = DataSourceFactory::createFromModel($this->getWorkbench(), %SourceUid%);        
	        $schema_installer = new MySqlDatabaseInstaller($this->getSelector());
	        $schema_installer
	        ->setFoldersWithMigrations(['InitDB','Migrations', 'DemoData'])
	        ->setFoldersWithStaticSql(['Views'])
	        ->setDataConnection($dataSource->getConnection());
	        $installer->addInstaller($schema_installer);
	        return $installer;
	    }
	}
	?>

## Core installers

- [SqlDatabaseInstaller](sql_database_installer.md)


