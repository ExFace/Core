# Recommended PHP settings

## TS or NTS version for Windows?

- Apache + LoadModule: Thread Safe (TS)
- Apache + FastCGI: Non-Thread Safe (NTS)
- IIS: Thread Safe
- IIS + FastCGI: Non-Thread Safe

PHP manual has nice [installation instructions for Windows servers](http://php.net/install.windows).

In general running PHP with FastCGI is the preferable way, it performs faster and allows for more fine-grained security configuration.

## Required extensions

These built-in extensions must be uncommented in the `php.ini`!

- `mbstring`
- `intl`
- `fileinfo`
- `sodium`
- `curl` and `openssl` unless you are definitely sure, that you will not need make any HTTP requests and will not use the `UrlDataConnector`

## Other extensions, that might be neede for optional functions

- `bz2`
- `ftp`
- `exif` and `gd2` for any image processing (e.g. thumbnails)
- `odbc`

## php.ini recommendations

The following setting are recommended in the `php.ini` file in your PHP directory. 

### General

1. Increase memory limit: `memory_limit = 1G`
2. Set the correct timezone in `date.Timezone` - e.g. `date.timezone = Europe/Berlin`
3. Enable and configure OPCache as shown below to increase performance
4. Logging
	- `log_errors = On`
	- `error_log = <path_to_error_log_file>` - Use `syslog` to log to the windows event viewer or an absolute path to 


### DEV environments

- `error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_DEPRECATED`
- `display_errors = On`

### PROD environments

- `error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_DEPRECATED`
- `display_errors = Off`
- `zend.exception_ignore_args = On` - do not show function parameters in traces to make sure no passwords are shown/logged
- `expose_php = Off` - hide PHP and its version from generic server responses
- `open_basedir = <path>` - Restrict where PHP processes can read and write on a file system

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

# Enables or disables copying of PHP code into HUGE PAGES. This should improve performance, 
# but requires appropriate OS configuration. Available on Linux.
opcache.huge_code_pages=1
```

### File uploads and large data

#### Upload size

If file uploads of significant size are a topic, check the following configuration

```
# Make sure the post_max_size is large enough for the entire size of all files being uploaded at once!
post_max_size=100M
```

Most facades upload files as part of regular data (encoded as base64). On the one hand, this means, it does not matter whether you upload a file or past some large text into an input field. On the other hand, you need to mak sure, POST requests can be large enough.

#### Actions with a lot of data

Large editable tables, trees and Gantt widgets may send a lot of data to the server. This might hit the `max_input_vars` limit in `php.ini`. If so, increase `max_input_vars `.

### Reducing file storage I/O

Use these values on systems with slow file storage (e.g. Azure App Services)

```
realpath_cache_size=4096K
realpath_cache_ttl=600

# When enabled, the opcode cache will be checked for whether a file has already been cached 
# when file_exists(), is_file() and is_readable() are called. This may increase performance 
# in applications that check the existence and readability of PHP scripts, but risks returning 
# stale data if opcache.validate_timestamps is disabled (see above)
opcache.enable_file_override=1
```