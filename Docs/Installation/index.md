# Installation

## Requirements

- Web server. The automatic installer supports
	- Apache 2.4+
	- Microsoft IIS
	- nginx
- PHP 8.2+ with
	- Required extensions: `sodium`, `zip`, `curl`, `intl`
	- Optional extensions:
        	- `gdlib` if resizing/editing images is required (e.g. for thumbnails in the `HttpFileServerFacade`)
- SQL database for the metamodel, users, permissions, etc. Supported engines are
	- MySQL 8+ (requires PHP extension `mysqli`)
	- MariaDB 10+ (requires PHP extension `mysqli`)
	- Microsoft SQL Server 2016+ (requires PHP extension `sqlsrv`)
	- PostgreSQL (requires PHP extension `pgsql`)

## Setting up the web server

These guides help set up a new web server or validate the configuration of an existing one.

- [WAMP server on Windows (Apache + MySQL)](WAMP.md) - typically for development
- [Microsoft IIS and SQL Server on Windows](IIS_with_SQL_Server.md) - recommended in Microsoft-oriented infrastructure if Windows VMs are used
- [nginx on Linux](nginx.md)
- [Apache on Linux](Apache.md)

## Installing the workbench

- [Install via PHP Composer](Install_via_Composer.md) on a single server.
- [Install remotely via Deployer](https://github.com/axenox/deployer/blob/1.x-dev/Docs/index.md) if you want manage multiple machines from a single build server.

## Starting up the first time

Open the URL to your installation folder in your browser (e.g. `http://localhost/exface`) and use the following initial credentials:

Username: `admin`
Password: `admin`

**IMPORTANT:** don't forget to change the admin password later on or disable the admin user entirely! Follow the below security guide to make sure your server and workbench are properly protected!

## Securing your installation

Make sure you follow IT security guidelines of your company! A web application is only as secure as its server. The workbench will auto-configure some basic settings, but security is always the responsibility of the server admin!

**IMPORTANT:** Follow are basic [security guideline](Server_security.md) to get started.