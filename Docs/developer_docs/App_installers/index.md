# Installing apps with AppInstallers

Every app class provides an installer (via `AppInterface::getInstaller()`) that takes care of initializing or removing non-PHP dependencies. This installer is called automatically once the app had been downloaded via PHP composer or if it is installed manually via Administration > Metamodel.

Installers are PHP classes, that implement the `\exface\Core\Interfaces\AppInstallerInterface`. In a nutshell, this interface makes the class behave similarly to a Windows installer executable by providing methods to install and uninstall an app. Additionally AppInstallers support a `backup()` method.

The core includes a couple of installer classes for common purposes like maintaining SQL databases, registering facades, etc. These installers can be easily used in every app. On the other hand, apps may provide custom and even reusable installers of their own.

## Adding an Installer to an app

To add an installer to an app modify the method `getInstaller()` in the app class. Create a new object of the desired installer, for example like: `$schema_installer = new MySqlDatabaseInstaller($this->getSelector())`. 

Configure the new installer and then add it to the AppInstaller like
`$installer->addInstaller($schema_installer);`

It is possible to add multiple installers like that to the AppInstaller. 

## Core installers

- [SqlDatabaseInstaller](sql_database_installer.md)


