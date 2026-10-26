# Secrets in environment variables

While data connections configs are encrypted by default, they are still explicitly visible to users with respective access rights. You can store secrets in environment variables to hide them from the UI completely. This will also allow to change these secrets without having access to the administration UI (which is useful for CI/CD pipelines).

To reference an environment variable in a data connection config, use the following syntax:

```
{
  "host": "localhost",
  "user": "",
  "password": "[#~env:ENV_VAR_NAME#]"
}
```
Where `ENV_VAR_NAME` is the name of the environment variable that contains the secret value.

## Choosing between ~env: and ~system:

Choose the placeholder according to **where you configure the variable**. You do
not need to change application code. Both forms work in data connection and
authenticator configurations.

| Where the value is configured | Placeholder | Example below |
| --- | --- | --- |
| In the environment of the service or process running the application | `[#~env:SQL_PASSWORD#]` | IIS FastCGI environment variables or a PHP-FPM pool |
| In the web server settings passed to the application with each request | `[#~system:SQL_PASSWORD#]` | Apache `SetEnv` or nginx `fastcgi_param` |

The examples below show matching server settings and placeholders. Some server
setups make a variable available through both forms, but do not rely on that:
**there is currently no automatic fallback between them**. Copy the variable name
exactly, including upper/lower case. A missing variable causes an error rather
than an empty password.

Use your real secret instead of `example-password`. Keep configuration files
containing secrets outside version control and restrict access to administrators.

## Apache

If Apache runs PHP as an Apache module (`mod_php`), add this directive inside the
site's existing virtual host configuration:

```apache
SetEnv SQL_PASSWORD "example-password"
```

This requires Apache's `mod_env` module. You can also put the directive in the
site's `.htaccess` if the server allows it (`AllowOverride FileInfo` or an
appropriate `AllowOverrideList`).

Reload Apache after editing the virtual host configuration. In the application's
connection configuration, use:

```json
{"password": "[#~system:SQL_PASSWORD#]"}
```

If Apache forwards requests to **PHP-FPM**, do not assume that `SetEnv` reaches
PHP-FPM. Use the [PHP-FPM pool example](#php-fpm-pool-alternative) below instead.

## nginx with PHP-FPM

In the site's existing nginx configuration, add this directive to the `location`
block that forwards PHP requests to PHP-FPM, alongside the existing FastCGI
parameters:

```nginx
fastcgi_param SQL_PASSWORD "example-password";
```

Keep the existing `include`, `fastcgi_pass` and other FastCGI settings; this line
is an addition, not a replacement for that configuration. Add it to every PHP
request handler used by the application.

Check the configuration with `nginx -t`, then reload nginx. In the application's
connection configuration, use:

```json
{"password": "[#~system:SQL_PASSWORD#]"}
```

Setting an environment variable on the nginx service alone is not sufficient:
nginx and PHP-FPM run as separate services. Either pass the value with
`fastcgi_param` as above or configure it in the PHP-FPM pool.

### PHP-FPM pool alternative

For either Apache or nginx using PHP-FPM, you can configure the secret directly
in the **PHP-FPM pool serving this application**:

```ini
env[SQL_PASSWORD] = "example-password"
```

Reload PHP-FPM after changing the pool configuration. In this case, use the
environment placeholder:

```json
{"password": "[#~env:SQL_PASSWORD#]"}
```

The explicit `env[...]` entry makes this variable available without disabling
PHP-FPM's environment filtering. All applications using this pool can access the
value; use a dedicated pool if applications must not share secrets.

## IIS with PHP FastCGI

Configure the variable on the FastCGI application used by your site:

1. Open **IIS Manager** and select the server.
2. Open **FastCGI Settings** and edit the entry for the `php-cgi.exe` executable
   used by the site's handler mapping.
3. Open the **EnvironmentVariables** collection and add an entry with
   **Name** `SQL_PASSWORD` and **Value** `example-password`.
4. Save the changes and recycle the site's application pool so new PHP processes
   receive the value.

In the application's connection configuration, use:

```json
{"password": "[#~env:SQL_PASSWORD#]"}
```

These settings apply to the selected FastCGI application, not just one website.
Check which sites share that configuration before adding a secret.

## Console commands and troubleshooting

Web server settings do not automatically apply to scheduled jobs or console
commands. If these use the same connection, also define `SQL_PASSWORD` in the
environment of the account or service launching those processes and use
`[#~env:SQL_PASSWORD#]` in their connection configuration. A connection configured
with `~system:` for web requests is not guaranteed to work unchanged in the console.

If a variable is reported missing, check its spelling, that the setting belongs
to the correct site or PHP service, and that the affected service was reloaded
or its worker processes restarted. Do not print the password in logs or expose
a diagnostic page listing environment variables.

Use administrator-controlled values for secrets, not values supplied by visitors
in request headers such as `HTTP_*`.

Server references:
[Apache SetEnv](https://httpd.apache.org/docs/2.4/mod/mod_env.html#setenv),
[nginx FastCGI parameters](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_param),
[PHP-FPM pool configuration](https://www.php.net/manual/en/install.fpm.configuration.php),
[IIS FastCGI environment variables](https://learn.microsoft.com/en-us/iis/configuration/system.webserver/fastcgi/application/environmentvariables/).