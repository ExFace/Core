# Recommended PHP settings

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