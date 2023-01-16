# IIS with SQL Server

## Installation

### PHP Installation as FastCGI module

To run PowerUI on an IIS server with SQL, configure the IIS server and install PHP as shown in one of these guides: 

- [Install on Windows Server 2016+ manually or via "Microsoft Web Platform Installer" (Web PI)](https://docs.microsoft.com/en-us/iis/application-frameworks/scenario-build-a-php-website-on-iis/configuring-step-1-install-iis-and-php)
- [Install on Windows Server 2008+ (IIS 7+) manually](https://docs.microsoft.com/en-us/iis/application-frameworks/install-and-configure-php-applications-on-iis/using-fastcgi-to-host-php-applications-on-iis)

##### Recommended PHP installation

Since the Web PI does not offer most recent versions of PHP, it is probably a good idea to install everything manually. 

1. Create the following folder structure
	- `C:\Program Files\PHP\`
		- `bin`
		- `logs`
		- `tmp`
		- `wincache` (if required)
2. Download one of the latest [PHP binaries](https://windows.php.net/download/) - pick the non-thread-safe (nts) version.
3. Unpack it into `C:\Program Files\PHP\bin`
4. Follow the guides above to register it as a FastCGI module in IIS
5. Give the users `IUSR` and `IIS AppPool\DefaultAppPool` read/write and modify permissions for the folders `tmp` and `logs`.
6. The other folders will be used in `php.ini` - see below.

### Rewrite Module installation

For the workbench to work properly, the support for rewrite rules needs to be enabled on the IIS server.

1. [Download UrlRewrite module](https://www.iis.net/downloads/microsoft/url-rewrite) 
2. Run the installer. No additional configuration is required.

### WinCache Extension Installation

The WinCache extension is recommended for PHP < 8 in addition to opcache. It accelerates PHP on IIS greatly. So far there is no WinCache for PHP 8.

1. [Download WinCache](https://sourceforge.net/projects/wincache/). Donwload the `nts` version if you have used the `nts` PHP binary above. Look in the `development` folder if you can't find your desired PHP version.
2. Unpack the files somewhere (e.g. `C:\Program Files\PHP\wincache`).
3. Copy `php_wincache.dll` to your PHP extensions-folder (e.g. `C:\Program Files\PHP\bin\ext`)
4. Add the extension to `php.ini` as shown below

Alternatively, you can install via CMD or PowerShell: 

`msiexec /i {WinCacheMsiPath} PHPPATH={PHPPath} IACCEPTWINDOWSCACHEFORPHPLICENSETERMS="Yes" /q` 

where `{WinCacheMsiPath}` is the path to the .msi file to install WinCache and `{PHPPath}` is the path to the php folder. **Important:** The php path must end with a trailing slash! If an error like `"PHPPATH property must be set to the directory where PHP resides"` occurs, try to install it via PowerShell.

### SQL Server extension

1. [Download sqlsrv extension](https://github.com/microsoft/msphpsql/releases) for your PHP version
2. Copy `php_sqlsrv_74_nts.dll` (or similar) to the `ext` folder of PHP. Use the `ts`/`nts` version according to your PHP binaries (see above)
3. Add the extension to `php.ini` as shown below

## php.ini Settings

There are a few settings that need to be changed or added to the `php.ini` file in your `PHP` directory. Rename `php.ini-development` or `php.ini-production` to `php.ini` to start with.

Use the following configuration in addition to the server-independent [recommendations](Recommended_PHP_settings.md).

**IMPORTANT**: Recycle your application pool in the IIS Manager to activate changes in `php.ini`!

1. Initial setup
	- `extension_dir = ./ext` - this is important! If not set, you might not be able to load extensions!
	- `cgi.force_redirect = 0`
	- `cgi.fix_pathinfo = 1`
	- `fastcgi.impersonate = 1`
	- `extension = sodium`
	- `sys_temp_dir = "C:\Program Files\PHP\tmp"` - If the path to your `tmp` folder is different change the path to the correct one!
2. Add SQL Server Extension:
	- `extension = sqlsrv_74_nts`
3. WinCache settings:
	- `extension = wincache`
	- `wincache.fcenabled = 1` (optional)
	- `wincache.ocenabled = 1` (optional)
4. OPCache settings:
	- `zend_extension = "C:\Program Files\PHP\bin\ext\php_opcache.dll"`
	- `opcache.enable = 1` and other settings as described in the general [PHP recommendations](Recommended_PHP_settings.md)
5. Recommended security-related settings
	- `fastcgi.logging = 0` (for dev-environment `1`) 
	- `display_errors = Off` (for dev-environment `On`) 
	- `log_errors = On`
	- `error_log = "C:\Program Files\PHP\logs\error.log"` - don't forget to crate the directory used here!
	
Check you PHP configuration by creating a file in `C:\inetpub\wwwroot` (e.g. `phpinfo.php`) and calling it in your browser via http://localhost/phpinfo.php. Search for `sqlsrv` in the output - if it is there, you are probably good to go. If not, loading extensions did not work yet - check your `extension_dir`, restart IIS, etc.
	
## Installing the workbench

### Create a folder

1. Open IIS Manager
2. Navigate to `<servername> > Sites > Default Web Site` on the left panel
3. Right click on `Default Web Site` and select `Add Virtual Directory`
4. Fill out the form 
	- The `Alias` will be the URL path to the workbench 
	- The `Physical path` is the actual location on the file system - e.g. `C:\inetpub\wwwroot\workbench`

This will automatically create the physical path.

**IMPORTANT**: the built-in user `IUSR` MUST have full access to the newly created folder! Otherwise many administration features will not work properly.

Now it is time to install the workbench via [Composer](Install_via_Composer.md) or the [deployer app](https://github.com/axenox/deployer/blob/1.x-dev/Docs/index.md) (if you already have a build server).

**IMPORTANT**: Don't forget to add the IIS-specific installer to the configuration file `System.config.json` to make sure the workbench has proper access to all files and folders it needs after installtion:

```
"INSTALLER.SERVER_INSTALLER.CLASS": "\\exface\\Core\\CommonLogic\\AppInstallers\\IISServerInstaller"

```

### Create a database

Create a separate database on the SQL server and assign a user to it. The user **must** have permissions to read and write data and to execute DDL statements lie `CREATE TABLE`, `CREATE VIEW`, etc.

You can use different types of authentication for the DB user - see documentation of the `MsSqlConnector` in `Administration > Documentations > Data Connectors`for more details.

**WARNING:** the credentials for the DB connection will be stored in the `System.config.json` unencrypted inside the workbench directory. You can avoid this if you use a Windows authentication. In this case, the credentials will only be stored in the IIS.

#### Set up SQL Server Windows authentication

Place the following in the `System.config.json`. No username or password needed!

```
{
	"METAMODEL.CONNECTOR_CONFIG": {
	    "host": "SERVER\\INSTANCE",
	    "database": "<database>",
	    "character_set": "UTF-8"
  	}
}
```

**IMPORTANT:** the PHP process must run as the user you need to authenticate with. Depending on the web
server used, different approaches are possible.

In the case of Microsoft IIS, the workbench needs to be installed in a "Virtual folder" in one of the
IIS application pools. The configuration of the pool seems not important, but in the settings of the
virtual folder, you need to specify the user and password:

1. Open IIS Manager
2. Navigate to `<servername> > Sites > Default Web Site` on the left panel
3. Right click on `Default Web Site` and select `Add Virtual Directory`
4. Fill out the form and press `Connect as...`
5. Select `Specific user` and press `Set...` right next to it
6. Type the user name with domain like `MYDOMAIN\User name` and that users current password

The workbench must be installed within the folder above. If you need to change the password, select your
created virtual directory on the left panel and press `Basic settings` on the right panel under `Actions`.

## Securing sensitive folders

See [security docs](../Security/Securing_installation_folders.md) for a list of folders to restrict access to.

## Add mime types to IIS configuration if required for facades

Different web facades use different file types and extensions. When installing a facade, check if all required files are allowed to be downloaded from the IIS - if not, add them to the MIME type mapping of IIS:

1. Open the IIS Manager
2. Select your server
3. Click on the `MIME Types` feature icon
4. Click on the `Add...` action on the right and map the required extension to a MIME type

For exampe, the `exface.UI5Facade` based on SAP UI5 uses `.properties` files for translations. This extension is not part of the standard IIS MIME mapping, so it needs to be added with MIME type `text/plain`.

To find out if MIME types are missing, look for `404`-errors in your browsers network debug tools (i.e. richt click > Inspect element).

## Update PHP version

To update the php version on the IIS Server follow this procedure:

1. Download the new PHP version (Non-Thread Safe)
2. Unzip it and copy the `php.ini` file from the current PHP/bin directory to the unzipped php directory
3. Compare the `php.ini-development` file in the new PHP directory with the copied `php.ini` file and copy missing entries into the php.ini file
4. Copy needed extensions from the current PHP `bin/ext` directory to the `ext` directory in the unzipped php directory or download new versions of those extensions if needed
5. In the current PHP directory rename the `bin` directory to `bin_{versionnumber}`, if thats not possible open the `Task Manager` and stop all PHP processes, for example `php-cgi.exe` and try renaming again
6. Create a new `bin` directory in the current PHP directory and copy everything from the unzipped PHP directory ito that directory
7. Restart the IIS Server