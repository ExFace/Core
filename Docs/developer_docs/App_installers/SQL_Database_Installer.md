# SQL Database Installer

- [Initializing the installer](SQL_Database_Installer.md#init)
- [Folder structure recommendations](SQL_Database_Installer.md#folders)
- [Migration files: syntax, name conventions, etc.](SQL_Database_Installer.md#migrations)
- [Configuration options](SQL_Database_Installer.md#config)
- [Skipping migrations](SQL_Database_Installer.md#skipping)
- [Installing demo data](SQL_Database_Installer.md#demodata)
- [Transaction handling](SQL_Database_Installer.md#transactions)

The SQL Database Installer is able to create databases and handle database migrations written in SQL. It works by running SQL scripts in from specific folders. 

Technically, the installer performs the following operations:

1. Create a database according to the given data connection (if it does not exist)
2. Execute migration SQL scripts, that were not yet applied to the DB schema yet and take down those, that were applied, but are not required anymore.
3. Execute any (static) SQL within files in specified folders - in constrast to migrations, this SQL will be executed on every install, regardless of whether it was executed before or not.

Migrations and static SQL are stored a plain SQL files in the app's folder structure. When instatiating the installer in the PHP code, the folders containing migrations and static SQL are specified, so the installe known, where to find what. While static SQL can be anything, migrations consist of two parts: the up-script (starting with the `-- UP` tag) and the down-script (starting with the `-- DOWN` tag). 

To handle migrations the installer creates a special migration log table in the database which contains informations about the current status of the database. This table contains information about every executed migration: it's content, when it was upped, when and if it was downed, etc. When an install operation is performed, the currently not-downed migrations are compared with those in the source folders. Migrations, that are not found in the DB, but exist in the folders, are upped, while migrations, that are up, but do not exist in the folders, are downed.

The installer runs first all scripts in the migration folder(s) and then all scripts in the static folder(s).

There is also a possibility to provide demo data with the help of a special migration folder - see chapter "Demo data" below.

## <a name="init"></a>Initializing the installer

Add something like this to the `getInstaller()` method of your app class:

```
...preceding installers here...
        
$schema_installer = new MySqlDatabaseInstaller($this->getSelector());
$schema_installer
    ->setDataSourceSelector('uid_of_target_data_source')
    ->setFoldersWithMigrations(['InitDB','Migrations', 'DemoData'])
    ->setFoldersWithStaticSql(['Views']);
$installer->addInstaller($schema_installer);
 
...subsequent installers here...
```

To set the folders containing files that should be run at first or should be migratable use the method `setFoldersWithMigrations()`. The order of the given folders is important as the installer will run the files in the folders in the order of the folders given. This means it is needed to first build the base database and then optionally fill it with Demo Data before applying any migrations. Therefore it is recommended to order the folders as follow:  
`setFoldersWithMigrations(['InitDB', 'DemoData', 'Migrations']);`.

To set the folders containing static sql files that should be run at every installation/repair use the method
`setFoldersWithStaticSql()`. The order of the given folders is important as the installer will run the files in the folders in the order of the folders given.

To set the DataConnection the installer should use, use the method `setDataConnection()`.

By default the migrations table will be named `_migrations`. You can set the name for the migrations table by using the method `setMigrationsTableName();`.

## <a name="folders"></a>Folder structure

It is recommended to use the following folder structure `%app%\install\Sql\%SqlDbType%\` in your app folders to save the sql files. For example for MySQL the folder structure would be `%app%\install\Sql\MySQL\`.

In addition it is recommended to have the following subfolders in `%SqlDbType%` folder:

- `InitDb` - for the initial database structure sql files
	- 01_tables.sql
	- 02_init_data.sql
- `DemoData` - for filling the database with demo data - see corresponding chapter below
- `Migrations` - for migration sql files containing UP and DOWN parts
	- 0.1 - Using version numbers for subfolders helps keep an overview
		- 20191130_1148_01_NEW_feature.sql
		- 20191130_1455_01_NEW_other_feature.sql
	- 0.2
		- 20191218_0822_01_FIX_feature.sql
- `Views` - views are typical static SQL - they can be recreated every time
	- 001_view1.sql
	- 002_view2.sql
- `Procedures`, etc. - for sql files containing scripts that need to be run at every installation
	
The installer will go through any subfolders in alphabetical order, therefore it is important that migration files are in the correct order and folders.

**NOTE**: The installer uses only the file names as identifiers for migrations, not the subfolder names. Each migration file MUST have a name unique within the app. This allows restructuring the folders without complications!

## <a name="migrations"></a>Migration files

It is recommended to name the migration sql files as follows `DATE_TIME_INDEX_INFO.sql` where:

- `DATE` - date migration file created
- `TIME` - time migration file created
- `INDEX` - number of migration, recommended to start with 01. Having this index allows to alter the migration without changing it's name - just increase the index and the old version will be downed and replaced by the new version.
- `INFO` - short info about the containing changes

Example for migration file name: `20190101_1200_01_NEW_column3_and_column4.sql`

For the installer to be able to revert changes applied my migrations it is needed to include revert scripts in your migration files when applying those migrations. It is needed to introduce the changing and reverting parts by certain keywords. By default those keywords are `-- UP` for changes and `-- DOWN` for reverts. Pay attention to the space between the `--` and `UP` or `DOWN`.

Example for migration file content:

```
-- UP
ALTER TABLE tablename
	ADD columnname3 datetime DEFAULT NULL,
	ADD columnname4 datetime DEFAULT NULL;
	
-- DOWN
ALTER TABLE tablename
	DROP columnname3,
	DROP columnname4;
```

## <a name="config"></a>Configuration options

By default, this installer offers the following configuration options to control it's behavior on a specific installation. These options can be added to the config of the app being installed.

- `INSTALLER.SQLDATABASEINSTALLER.DISABLED` - set to TRUE to disable this installer completely (e.g. if you wish to manage the database manually).
- `INSTALLER.SQLDATABASEINSTALLER.SKIP_MIGRATIONS` - array of migration names to skip for this specific installation (see below).

If an app contains multiple SQL-installers, the config option namespace may be changed when instantiatiating the installer via setConfigOptionNamePrefix(), so that each installer can be configured separately. If not done so, each option will affect
all SQL-installers. 
		
## <a name="skipping"></a>Skipping Migrations

There are two options to skip migrations during installation or roll back already performed and still applied migrations.

First option is to just delete the `.sql` files of the migrations you want to skip/rollback in the folder.

Second option is to keep the files in the folders and tell the installer what files it should skip.  
To do so create, if not already existing, a `config` folder in the base folder of the App and in that folder create, if not already existing, a config `.json` file. The file needs to be named like `%vendor%.%AppName%.config.json` where the App Name needs to be without spaces. So for an App called `Demo MES` with the vendor `powerui` the config file would be named `powerui.DemoMES.config.json`. 
In this file include the option `"INSTALLER.SQLDATABASEINSTALLER.SKIP_MIGRATIONS":` and in square brackets, in quotation marks, separated by commas add the file names of the migrations you want to skip or rollback. The file name needs to include the `.sql` ending but not the order structure.  
Example for two migration files that should be skipped/rolled back:

```
{
  "INSTALLER.SQLDATABASEINSTALLER.SKIP_MIGRATIONS": [
    "20190101_1200_01_NEW_column3_and_column4.sql",
    "20190102_1300_02_NEW_column5_and_column6.sql"
  ]	
}	
```

It is possible to change the option name by calling the method `setSqlMigrationsToSkipConfigOption`. 

## <a name="demodata"></a>Installing demo data

Sometimes it is usefull to provide demo data for an application, so the user does not start with a blank screen. Since the demo data only needs to be imported once, it's just a set of migrations from the point of view of the installer. 

If you want to put the demo data into a separate folder (which is generally a good idea), create a `DemoData` folder and register it as a migration folder in the installer (see above). Use the general migration naming conventions for demo data too to be able to add changes easily.

**IMPORTANT**: Keep in mind, that the migrations from the `DemoData` folder will be executed after ALL structural migration are applied. This means, that the demo data SQL MUST be compatible to the schema produced by the latest migration! Otherwise installing the demo data on a fresh DB will not work. 

Technically, this implies, that if a structural migration affects the demo data, all demo files must be updated. Do not rename them in this case! Just update the SQL inside. The changes are only needed for those installations, that do not have the affected part of demo data. Those, that had alreade received that demo data will get handled by structural migrations anyway.

## <a name="transactions"></a>Transaction handling

Transaction handling is different depending on the concrete installer implementation.

### DDL Transactions in major DBMS

- PostgreSQL - yes
- MySQL - no; DDL causes an implicit commit
- Oracle Database 11g Release 2 and above - by default, no, but an alternative called edition-based redefinition exists
- Older versions of Oracle - no; DDL causes an implicit commit
- SQL Server - yes
- Sybase Adaptive Server - yes
- DB2 - yes
- Informix - yes
- Firebird (Interbase) - yes
