# Installation

## Install via composer

TODO

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

## How to setup a compatible web server

1. [Windows with WAMP server](WAMP.md)
2. [Windows server with IIS and SQL Server](IIS_with_SQL_Server.md)