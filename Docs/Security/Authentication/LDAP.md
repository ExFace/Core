# Authentication via LDAP and LDAPS

The core includes the `LdapAuthenticator` for single-sign-on with LDAP. It uses the PHP `ldap` extension directly (make sure `extension=ldap` is enabled in `php.ini`).

**NOTE:** the former `SymfonyLdapBindAuthenticator` is deprecated and now only an alias for the `LdapAuthenticator`. Its `dn_string` is ignored - the `LdapAuthenticator` default `[#domain#]\[#username#]` is used instead, which matches the previous behavior. Please switch existing configurations to the `LdapAuthenticator`.

## Examples

### LdapAuthenticator + creating new users with static roles

```
{
	"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\LdapAuthenticator",
	"host": "MYLDAP",
	"domains": [
		"mydomain"
	],
	"create_new_users": true,
	"create_new_users_with_roles": [
		"exface.Core.SUPERUSER"
	]
}
```

Place the domain name of your LDAP server (or it's IP address) in the `host` property
and list all domains available for logging in to under `domains`.

If `create_new_users` is `true`, a new workbench user will be created automatically once
a new username is authenticated successfully. These new users can be assigned some roles
under `create_new_users_with_roles`. 

If a new user is not assigned any roles, he or she will only have access to resources
available for the user roles `exface.Core.ANONYMOUS` and `exface.Core.AUTHENTICATED`.

### LdapAuthenticator with LDAPS or custom ports

```
{
	"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\LdapAuthenticator",
	"host": "ldaps://MYLDAP:636",
	"domains": [
		"mydomain"
	]
}
```

If getting `Can't contact LDAP server` errors with a self-signed SSL certificate, try disabling certificate verification:

- on Windows create the file `C:\OpenLDAP\sysconf\ldap.conf` with a single line `TLS_REQCERT never`.
- on Linux add the same line to `/usr/local/openldap/etc/openldap/ldap.conf`
- **restart** the web server (e.g. Apache)!

**NOTE:** disabling certifcate validation makes the server vulnurable to man-in-the-middle attacks!

