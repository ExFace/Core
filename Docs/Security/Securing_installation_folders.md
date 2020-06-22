# Securing installation folders

It is important to restrict access to certain folders in the installation. This can be done in different ways depending on the web server you are using.

## Sensitive files and folders

In the installation directory:

- `/config` - all files here should be protected, especially `System.config.json`.
- `/logs` - logs may contain sensitive information. If you need to access log files via URL, write an action to publish them through an HTTP facade, but do not allow direct access to the files.
- `/data` - contains files of users and those created by apps like exporters, deployers, etc. Generally you don't want them to be accessible directly via URLs. 
    - `.contexts.json` files inside the data folder contain context data of various scopes. They should definitely be protected!
- `/backup` - less important, but still there is no point in accessing the contents from outside
- `/translations` - less important, but still there is no point in accessing the contents from outside

## Example configuration for Apache servers

In the case of an Apache server, the `.htaccess` file is a handy tool to restrict access to specific URLs. See the [default .htaccess template](../../Install/default.htaccess) in the Core app for an example configuration.