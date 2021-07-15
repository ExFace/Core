# IIS with SQL Server

## PHP Installation with FastCGI module

To run PowerUI on an IIS server with SQL, configure the IIS server and install PHP as shown in one of these guides: 

- [Install via "Microsoft Web Platform Installer" (Web PI) or manually on Windows Server 2016+](https://docs.microsoft.com/en-us/iis/application-frameworks/scenario-build-a-php-website-on-iis/configuring-step-1-install-iis-and-php)
- [Install manually on Windows Server 2008+ (IIS 7+)](https://docs.microsoft.com/en-us/iis/application-frameworks/install-and-configure-php-applications-on-iis/using-fastcgi-to-host-php-applications-on-iis)

### Recommended PHP installation

Since the Web PI does not offer most recent versions of PHP, it is probably a good idea to install everything manually. 

1. Download one of the latest [PHP binaries](https://windows.php.net/download/) - pick the non-thread-safe (nts) version.
2. Unpack it into `C:\Program Files\PHP\bin`
3. Follow the guides above to register it as a FastCGI module in IIS
4. Create a `tmp` folder in your PHP folder. (`C:\Program Files\PHP\tmp`)
5. Give the users `IUSR` and `IIS AppPool\DefaultAppPool` read/write and modify permissions for the `tmp` folder.

## Rewrite Module Installation

For the workbench to work properly, the support for rewrite rules needs to be enabled on the IIS server.

1. [Download UrlRewrite module](https://www.iis.net/downloads/microsoft/url-rewrite) 
2. Run the installer. No additional configuration is required.

## WinCache Extension Installation

The WinCache Extension is definitely recommended. It accelerates PHP on IIS greatly (comparable to opcache on Apache-based servers).

1. [Download WinCache](https://sourceforge.net/projects/wincache/). Donwload the `nts` version if you have used the `nts` PHP binary above. Look in the `development` folder if you can't find your desired PHP version.
2. Unpack the files somewhere (e.g. `C:\Program Files\PHP\wincache`).
3. Copy `php_wincache.dll` to your PHP extensions-folder (e.g. `C:\Program Files\PHP\bin\ext`)
4. Add the extension to `php.ini` as shown below

Alternatively, you can install via CMD or PowerShell: 

`msiexec /i {WinCacheMsiPath} PHPPATH={PHPPath} IACCEPTWINDOWSCACHEFORPHPLICENSETERMS="Yes" /q` 

where `{WinCacheMsiPath}` is the path to the .msi file to install WinCache and `{PHPPath}` is the path to the php folder. **Important:** The php path must end with a trailing slash! If an error like `"PHPPATH property must be set to the directory where PHP resides"` occurs, try to install it via PowerShell.

## SQL Server extension

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
	- `wincache.fcenabled=1` (optional)
	- `wincache.ocenabled=1` (optional)
4. OPCache settings:
	- `zend_extension = C:\Program Files\PHP\bin\ext\php_opcache.dll`
	- `opcache.enable = 1` and other settings as described in the general [PHP recommendations](Recommended_PHP_settings.md)
5. Recommended security-related settings
	- `fastcgi.logging = 0` (for dev-environment `1`) 
	- `display_errors = Off` (for dev-environment `On`) 
	- `log_errors = On`
	- `error_log = c:\Program Files\PHP\logs\error.log` - don't forget to crate the directory used here!
	
## Installing the workbench

Now it is time to install the workbench via [Composer](Install_via_Composer.md) or the [deployer app](https://github.com/axenox/deployer/blob/1.x-dev/Docs/index.md) (if you already have a build server).

**IMPORTANT**: Don't forget to add the IIS-specific installer to the configuration file `System.config.json` to make sure the workbench has proper access to all files and folders it needs after installtion:

```
"INSTALLER.SERVER_INSTALLER.CLASS": "\\exface\\Core\\CommonLogic\\AppInstallers\\IISServerInstaller"

```

## Securing sesnsitive folders

See [security docs](../Security/Securing_installation_folders.md) for a list of folders to restrict access to.

## Add mime types to IIS configuration if required for facades

Different web facades use different file types and extensions. When installing a facade, check if all required files are allowed to be downloaded from the IIS - if not, add them to the MIME type mapping of IIS:

1. Open the IIS Manager
2. Select your server
3. Click on the `MIME Types` feature icon
4. Click on the `Add...` action on the right and map the required extension to a MIME type

For exampe, the `exface.UI5Facade` based on SAP UI5 uses `*.properties` files for translations. This extension is not part of the standard IIS MIME mapping, so it needs to be added with MIME type `text/plain`.

To find out if MIME types are missing, look for `404`-errors in your browsers network debug tools (i.e. richt click > Inspect element).