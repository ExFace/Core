# Authentication via LDAP

The core includes two different authenticators to use for single-sign-on with LDAP:

- `LdapAuthenticator` - a simple, easy to use implementation for most common cases
- `SymfonyLdapBindAuthenticator` - a more advanced authenticator built on-top of the Symfony LDAP component. It allows more configuration. There is also a lot of documentation on the Symfony LDAP component.

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

### SymfonyLdapBindAuthenticator + creating new users with static roles

```
{
	"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\SymfonyLdapBindAuthenticator",
	"host": "MYLDAP",
	"dn_string": "{username}",
	"domains": [
		"mydomain"
	],
	"create_new_users": true,
	"create_new_users_with_roles": [
		"exface.Core.SUPERUSER"
	]
}
```

