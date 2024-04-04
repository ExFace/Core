# Installation

## Requirements

- Web server. The automatic installer supports
	- Apache 2.4+
	- Microsoft IIS
- PHP 7.2+ with
	- `sodium` extension
- SQL database for the metamodel, users, permissions, etc. Supported engines are
	- MySQL 5.7+
	- MariaDB 10+
	- Microsoft SQL Server 2016+ (requires PHP extension `sqlsrv`)

## Setting up the web server

These guides help set up a new web server or validate the configuration of an existing one.

- [Windows with WAMP server](WAMP.md)
- [Windows server with IIS and SQL Server](IIS_with_SQL_Server.md)

## Installing the workbench

- [Install via PHP Composer](Install_via_Composer.md) on a single server.
- [Install remotely via Deployer](https://github.com/axenox/deployer/blob/1.x-dev/Docs/index.md) if you want manage multiple machines from a single build server.

## Starting up

Open the URL to your installation folder in your browser (e.g. `http://localhost/exface`) and use the following initial credentials:

Username: `admin`
Password: `admin`

**IMPORTANT:** don't forget to change the admin password later on or disable the admin user entirely!