# IIS with SQL Server

## PHP Installation with FastCGI module

To run PowerUI on an IIS server with SQL, configure the IIS server and install PHP as shown in this guide: [Host PHP Applications on IIS](https://docs.microsoft.com/en-us/iis/application-frameworks/install-and-configure-php-applications-on-iis/using-fastcgi-to-host-php-applications-on-iis)

## Rewrite Module Installation
For PowerUI to work, the support for rewrite rules needs to be enabled on the IIS server. For this the rewrite module needs to be installed.
Download the module here:

- [Download x86 Version](https://go.microsoft.com/?linkid=9722533)
- [Download x86 Version](https://go.microsoft.com/?linkid=9722532)

## WinCache Extension Installation
The WinCache Extension for php is required. Donwload the correct `nts` version, fitting to your php and Windows version, here:
- [WinCache Download](https://sourceforge.net/projects/wincache/)

Install the module, if an error like `"PHPPATH property must be set to the directory where PHP resides"` occurs, try to install it via PowerShell.

The command is `msiexec /i {WinCacheMsiPath} PHPPATH={PHPPath} IACCEPTWINDOWSCACHEFORPHPLICENSETERMS="Yes" /q`

`{WinCacheMsiPath}` is the path to the .msi file to install WinCache and `{PHPPath}` is the path to the php folder.

**Important:** The php path must end with a trailing slash!

## SQL Driver Installation
Download the driver package from the Microsoft website:
- [SQL Driver](https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server?view=sql-server-ver15).
Copy the two `.ddl`-files corresponding your php version and windows version, starting with `php_pdo` and `php_sqlsrv`, into the `ext` subfolder of your `PHP` directory. The `nts` versions of those fiels are needed.
So for a php version `7.2.18` and a windows x64 version the files `php_pdo_sqlsrv_72_nts_x64.dll` and `php_sqlsrv_72_nts_x64.dll` are needed.

## php.ini Settings
Their are a few settings that need to be changed or added to the `php.ini` file in your `PHP` directory.

1. Deactivate memory limit:
	- `memory_limit = -1`
2. Activate SQL Driver Extensions by adding the entries matching the two SQL driver files copied to the `ext` directory, for example:
	- `extension=php_pdo_sqlsrv_73_nts_x64.dll`
	- `extension=php_sqlsrv_73_nts_x64.dll`
3. WinCache settings:
	- `extension=php_wincache.dll`
	- `wincache.fcenabled=1`
	- `wincache.ocenabled=1`
4. Activiate Sodium extension:
	- `extension=sodium`
5. Opache Settings:
	- `zend_extension=php_opcache.dll`
	- `opcache.enable=On`
	- `opcache.enable_cli=On`
	- add the settings shown [HERE](https://wiki.salt-solutions.de/pages/viewpage.action?pageId=162169402# Installation/UpdatedesWAMP-Servers-KonfigurationvonOPCache)
	
## Securing sesnsitive folders

See [security docs](../Security/Securing_installation_folders.md) for a list of folders to restrict access to.