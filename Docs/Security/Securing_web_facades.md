# Securing web facades

The internet is not a safe place. While the HTTP facades mostly come with a decently secure configuration, you can still improve it specifically for your server environment. Here are a few options to concider.

## HTTP headers

### Recommended headers for secure sites (HTTPS)

Place in the facade config (e.g. `config/exface.JEasyUIFacade.config.json`):

```
{
	"FACADE.HEADERS.CONTENT_SECURITY_POLICY.FLAGS": "upgrade-insecure-requests; block-all-mixed-content"
}
```

## Hiding error details

To hide error details add/edit the following lines in the `php.ini` of the server to the given values:

```
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

Also place in the system config (`config/System.config.json`) the following:

```
{
	"DEBUG.PHP_ERROR_REPORTING": "E_ALL & ~E_DEPRECATED & ~E_STRICT"
}
```