# Recommended PHP settings

## TS or NTS version for Windows?

- Apache + LoadModule: Thread Safe (TS)
- Apache + FastCGI: Non-Thread Safe (NTS)
- IIS: Thread Safe
- IIS + FastCGI: Non-Thread Safe

PHP manual has nice [installation instructions for Windows servers](http://php.net/install.windows).

In general running PHP with FastCGI is the preferable way, it performs faster and allows for more fine-grained security configuration.

## Required extensions

- `mbstring`
- `intl`
- `fileinfo`
- `sodium`
- `curl` and `openssl` if you plan to use the `UrlDataConnector`

These built-in extensions must be uncommented in the `php.ini`!

## php.ini recommendations

The following setting are recommended in the `php.ini` file in your PHP directory. 

1. Increase memory limit:
	- `memory_limit = 1G`
2. Enable `opcache` or `wincache` - see the server-specific installation guides for details. 
3. Security-related options
	- `display_errors = Off`
	- `log_errors = On`
	- `error_log = <path_to_error_log_file>` - Use `syslog` to log to the windows event viewer or an absolute path to a log file. 
	- `open_basedir = <path>` - Restrict where PHP processes can read and write on a file system
4. Set the correct timezone in `date.Timezone` - e.g. `date.timezone = Europe/Berlin`
5. Enable and configure OPCache as shown below to increase performance

### Enabling OPCache

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