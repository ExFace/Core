# Single-Sign-On with data connections

TODO

## Examples

### Authentication + create new users with static roles

This configuration will automatically create a workbench user with `SUPERUSER` role
if valid credentials for the metamodel's DB connection are provided.

```
{
	"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\DataConnectionAuthenticator",
	"connection_aliases": [
		"exface.Core.METAMODEL_CONNECTION"
	],
	"create_new_users": true,
	"create_new_users_with_roles": [
		"exface.Core.SUPERUSER"
	]
}
```

If you specify multiple connections, the user will be able to choose one berfor logging in.

If `create_new_users` is `true`, a new workbench user will be created automatically once
a new username is authenticated successfully. These new users can be assigned some roles
under `create_new_users_with_roles`. 

If a new user is not assigned any roles, he or she will only have access to resources
available for the user roles `exface.Core.ANONYMOUS` and `exface.Core.AUTHENTICATED`.

