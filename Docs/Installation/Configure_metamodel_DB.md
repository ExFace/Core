# Seting up a DB for the metamodel

## MySQL

```
{
	"METAMODEL.LOADER_CLASS": "\\exface\\Core\\ModelLoaders\\MySqlModelLoader",
	"METAMODEL.QUERY_BUILDER": "\\exface\\Core\\QueryBuilders\\MySqlBuilder",
	"METAMODEL.CONNECTOR": "\\exface\\Core\\DataConnectors\\MySqlConnector",
	"METAMODEL.CONNECTOR_CONFIG": {
		"host": "127.0.0.1",
		"user": "root",
		"password": "",
		"dbase": "",
		"charset": "utf8"
	}
}
```

## Microsoft SQL Server

**NOTE:** to install the model DB on SQL server your MUST enable the [sqlsrv PHP extension](https://github.com/microsoft/msphpsql/releases) before you start the installer! The extension MUST be enabled for the command line too. 

Also make sure, the Microsoft ODBC drivers are installed. 

```
{
	"METAMODEL.LOADER_CLASS": "\\exface\\Core\\ModelLoaders\\MsSqlModelLoader",
	"METAMODEL.QUERY_BUILDER": "\\exface\\Core\\QueryBuilders\\MsSqlBuilder",
	"METAMODEL.CONNECTOR": "\\exface\\Core\\DataConnectors\\MsSqlConnector",
	"METAMODEL.CONNECTOR_CONFIG": {
		"host": "127.0.0.1",
		"user": "sa",
		"password": "",
		"dbase": "",
		"charset": "UTF-8"
	}
}
```

### Troubleshooting

#### Error "Named Pipes Provider: Could not open a connection to SQL Server"

The `host` in the configuration above could not be resolved. Try using the machines network name instead of the IP or vice versa. Also see if you are trying to contact a named SQL server - if so, add it's name to the `host` separated by a slash.

## MariaDB

```
{
	"METAMODEL.LOADER_CLASS": "\\exface\\Core\\ModelLoaders\\MariaDbModelLoader",
	"METAMODEL.QUERY_BUILDER": "\\exface\\Core\\QueryBuilders\\MariaSqlBuilder",
	"METAMODEL.CONNECTOR": "\\exface\\Core\\DataConnectors\\MariaDbSqlConnector",
	"METAMODEL.CONNECTOR_CONFIG": {
		"host": "127.0.0.1",
		"user": "root",
		"password": "",
		"dbase": "",
		"charset": "utf8"
	}
}
```