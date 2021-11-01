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

TODO