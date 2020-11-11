# Setting up an SQL DB and a data source in the model

#### [< Previous](02_Creating_a_new_app.md) | [BookClub tutorial](index.md) | [Next >](04_Generating_a_model_from_an_SQL_schema.md)

Now that we have an [app](02_Creating_a_new_app.md), we need to let the workbench know, that our [newly created database](01_Preparation.md) is a data source this app can access. 

A data source basically consists of two things:
- a data connection, which contains all the information required to establish a connection
- a query builder that allows the workbench to talk to the data source.

Note, that multiple connections can be used for a data source: e.g. separate databases for staging and production are different connections for the same data source - namely our SQL DB.

Data sources can be very different: databases, web services, files, etc. The logic of the data source is represented by the combination of query builder and connection. The two, of course, must be compatible. You can find the documentation for all available query builders and connectors (connection types) in the corresponding subsections of the menu under `Administration > Documentation`.

Since we need to tell our data source, which connection to use, we will start with creating the connection first.

## 1. Create a data connection

Connections can be very different too: after all, it's quite a difference if you connect to an SQL DB or a web service. Yet both - a database and a web service - can be configured as a connection in the metamodel. The difference lies in the "connection prototype" (or simply "connector") used. The prototype is basically a PHP class, that actually establishes the connection by interpreting all the configuration we provide in the connection model. 

Let's create a data connection to our new DB:

1. Navigate in the main menu to `Administration > Metamodel > Connections`. Here you can see all data connections the workbench can currently use.
2. Press the `+ New` button in the table with the connections.
3. Fill the dialog with the following values:
	- Connection Name: `BookClub SQL DB localhost`
	- Is part of app: `tutorial.BookClub`
	- Connection Alias: `BOOKCLUB_DB_LOCAL`
	- Connector: `MySqlConnector`
4. Press the big magic-wand-icon in the middle of the configuration area
5. Select `MySQL connection with relation matcher` from the list of available presets and press the `Replace` button at the bottom of the dialog
6. Fill the configuration values as follows:
	- host: `127.0.0.1` (using an IP is faster than a network name in most cases!)
	- dbase: `tutorial_bookclub`
	- charset: `utf8`
	- user: the username to connect to the MySQL server
	- password: the password for the user above
	- relation_matcher: leave as is (see explanation below)
7. Press `Save`

![Data Connection](Images/connection.png)

Our new connection should now appear at the top of the list.

The `MySqlConnector` prototype, that we use here works for MySQL databases as the name suggests. It's configuration (host, dbase, etc.) is also specific to MySQL and may look very different for another data source or even another database engine. You can find the documentation for all available connectors in the main menu at `Administratyion > Documentation > Data Connectors`.

The idea of prototypes with their specific hierarchical configuration is a central concept. There are prototypes for a lot of things: widgets, actions, data types, etc. The configuration is done via our modeling language called [UXON](../../Creating_UIs/UXON/index.md) (User eXperience Object Notation) in special [editors](../../Creating_UIs/UXON/Introduction_to_the_UXON_editor.md), that provide autosuggest, presets (the magic wand above), quick refference, etc. The editor knows, what configurable properties each prototype has and can even suggest possible values in many cases.

## 2. Create a data source

Now that we have a connection, we can use it in a data source:

1. Navigate in the main menu to `Administration > Metamodel > Data Sources`. Here you can see all data sources with their current connections.
2. Press the `+ New` button in the table with the data sources.
3. Fill the dialog with the following values:
	- Data Source Name: `BookClub DB`
	- Is part of app: `tutorial.BookClub`
	- Data Source Alias: `BOOKCLUB_DB`
	- Default Connection: `BookClub SQL DB localhost`
	- Default Query Builder: `MySqlBuilder`
4. Press `Save`

![Data Connection](Images/datasource.png)

Now we have a data source in the model of the app `tutorial.BookClub` which uses our database as it's default connection.

Note, that apart from the default connection and query builder (that we have filled above) there is an option to switch temporarily to another set of the two - the "current" connection and query builder. The difference is that the defaults are saved in the app's package and thus will be transferred to another server together with the app. The "current" ones are local settings only and will only have effect on this particular server.

Read more about data sources and connections in the [model docs](../../creating_metamodels/data_sources/index.md).

### [< Previous](02_Creating_a_new_app.md) | [BookClub tutorial](index.md) | [Next >](04_Generating_a_model_from_an_SQL_schema.md)