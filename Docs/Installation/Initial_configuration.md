# Initial configuration

Check out `vendor/exface/Core/Config/System.config.json` for available global configuration options. Just copy any option into your `Config/System.config.json` to overwrite it. 

Note that apps also can have their specific configuration files locateded in the apps folder. The `System.config.json` above contains the global configuration, that is valid for the entire installation with all its apps and users.

## General settings

- `SERVER.DEFAULT_LOCALE` - the default language/local to use for new users
- `SERVER.TITLE` and `SERVER.TITLE_HTML` - how your installation is called

## Server installers

Depending no the web server being used, an additional installer may be needed to take care of redirects, file and folder permissions, etc.:

- On Microsoft IIS add `"INSTALLER.SERVER_INSTALLER.CLASS": "\\exface\\Core\\CommonLogic\\AppInstallers\\IISServerInstaller"` to `System.config.json`.

## Authentication

By default, users must be created manually in `Administration > Users & Security`. In most cases, it is very handy to add single-sign-on with other applications, LDAP, Azure, etc. by extending and overwriting `SECURITY.AUTHENTICATORS` in your configuration - see [Authentication docs](../Security/Authentication/index.md) for more details.

## Production environment

- Reduce error output: `"DEBUG.PRETTIFY_ERRORS": false`
- Enable the error monitor to track errors nicely in a dashboard uner `Administration > Monitor`: `"MONITOR.ENABLED": true`
	- NOTE: this also enables the action monitor that tracks actions being triggered by users and their performance. If you only want to use the error monitor (without further tracking), add `"MONITOR.ACTIONS.ENABLED": false`

## Development environment

- Enable verbose error output: `"DEBUG.PRETTIFY_ERRORS": true`