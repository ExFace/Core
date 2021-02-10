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

To install the workbench on MS SQL Server you will need to create a table 

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