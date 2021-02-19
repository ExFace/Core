# Setting up WAMP server on Windows

## Download and install

## Required configuration

### PHP extension `sodium`

TODO 

### PHP extension `sqlsrv` if you plan to use Microsoft SQL Server for model DB

To install the model DB on SQL server your MUST enable the [sqlsrv PHP extension](https://github.com/microsoft/msphpsql/releases) before you start the installer! The extension MUST be enabled for the command line too.

1. Download the extension for your PHP version [here](https://github.com/microsoft/msphpsql/releases).
2. Copy the file `php_sqlsrv_74_ts.dll` (or any other version) to `wamp/bin/php/<version>/ext`
3. Add the following line to `wamp/bin/php/phpForApache.ini` **AND** `wamp/bin/php/phpForApache.ini` (the latter being used for CLI) somewhere among the other extensions: `extension=php_sqlsrv_74_ts.dll`
4. Restart all services in WAMP

## Recommended PHP settings

### Enable OPcache

The following OPCache configuration is recommended in the `php.ini`:

```
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.max_wasted_percentage=5
opcache.use_cwd=1
# use "0" for revalidate frequency if you plan active development
opcache.revalidate_freq=30
opcache.revalidate_path=1
opcache.validate_timestamps=1
opcache.save_comments=1
```

## Installing additional PHP extensions

TODO