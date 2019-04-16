#SQL Database Installer

##General  function
The SQL Database Installer is able to create databases and handle database migrations, It works by running SQL scripts in specified folders. To handle migrations it creates a special migration table in the database which contains informations about the current status of the database. To do so the Installer writes information about every sql script saved in files in the migrations folders in the migrations table.

For the installer to be able to revert database changes the .sql file containing those changes also needs to contain the sql statements to reverse these changes. To revert changes applied from such a file just delete that file from the App folder and rerun the installer of the app.

The installer runs first all scripts in the migration folder(s) and then all scripts in the static folder(s)

##Configure installer
To set the folders containing files that should be run at first or should be migratable use the method `setFoldersWithMigration();`.
The order of the given folders is important as the installer will run the files in the folders in the order of the folders given. This means it is needed to first build the base database and then optionally fill it with Demo Data before applying any migrations. Therefore it is recommended to order the folders as follow:
`setFoldersWithMigrations(['InitDB', 'DemoData', 'Migrations']);`.

To set the folders containing static sql files that should be run at every installation/repair use the method
`setFoldersWithStaticSql();`.
The order of the given folders is important as the installer will run the files in the folders in the order of the folders given.

To set the DataConnection the installer should use, use the method `setDataConnection()`.

By default the migrations table will be named `_migrations`. You can set the name for the migrations table by using the method `setMigrationsTableName();`.


##Important Hints

###Folder structure

It is recommended to use the following folder structure `%app%\install\Sql\%SqlDbType%\` in your app folders to save the sql files. For example for MySQL the folder structure would be `%app\install\Sql\MySQL\`.

In addition it is recommended to have the following subfolders in `%SqlDbType%` folder:
	- InitDb - for the initial database structure sql files
	- DemoData - for Demo Data sql files you want to fill the database with
	- Migrations - for migration sql files containing UP and DOWN parts
	- Views, Procedures, etc. - for sql files containing scripts that need to be run at every installation
	
The installer will go through any subfolders in alphabetical order, therefore it is important that migration files are in the correct order and folders.

###Migration files

It is recommended to name the migration sql files named as follows `DATE_TIME_NUMBER_INFO.sql` where:
	DATE - date migration file created
	TIME - time migration file created
	NUMBER - number of migration, recommended to start with 01
	INFO - short info about the containing changes
Example for migration file name: `20190405_091839_01_NEW_column3_und_column4.sql`

For the installer to be able to revert changes applied my migrations it is needed to include revert scripts in your migration files when applying those migrations. It is needed to introduce the changing and reverting parts by certain keywords. By default those keywords are `-- UP` for changes and `-- DOWN` for reverts. 

Example for migration file content:
	-- UP
	ALTER TABLE tablename
		ADD columnname3 datetime DEFAULT NULL,
		ADD columnname4 datetime DEFAULT NULL;
	-- DOWN
	ALTER TABLE tablename
		DROP columnname3,
		DROP columnname4;