# Single-Sign-On via SQL

The `SQLAuthenticator` allows to verify username and password by performing an SQL statement in a given data connection. This way single-sign-on can be implemented with any software, that stores password hashes in SQL.

## Examples

### Authentication + create new users with static roles

This configuration will automatically create a workbench user with `SUPERUSER` role
if valid credentials for the metamodel's DB connection are provided.

```
{
	"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\SQLAuthenticator",
	"connection_aliases": [
		"my.App.db_connection"
	],
      "sql_to_check_password": "SELECT id FROM users_table WHERE user_name = '[#username#]' AND password_hash = PASSWORD('[#password#]')",
      "sql_to_get_user_data": "SELECT first_name AS FIRST_NAME, last_name AS LAST_NAME FROM users_table WHERE id = '[#id#]'",
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

