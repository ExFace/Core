<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Security\AuthenticationToken\DataConnectionUsernamePasswordAuthToken;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * Authenticates users by performing an SQL statement in specified data connection.
 * 
 * Allows single-sign-on with any application that stores password hashes in SQL.
 * 
 * Basically, you need to provide the SQLs to verify the password and to read user data with some placeholders:
 * - `sql_to_check_password` must return a non-empty result if the placeholders `[#username#]` and `[#password#]`
 * match the stored credetials. The returned data can by anything. But it MUST  be empty (no rows) if the password
 * does not match. If it does match, any returned columns can be used as placeholders in the subsequet query to
 * read user data.
 * - `sql_to_get_user_data` may return user data like `FIRST_NAME`, `LAST_NAME`, `EMAIL`. The column names must
 * match attributes of the `exface.Core.USER` object. This allows to read the user data and create new users
 * on the fly. Placeholders can be used in the SQL: any column retured by `sql_to_check_password` is available.
 * 
 * If you specify multiple connections, the user will be able to choose one berfor logging in.
 * 
 * ## Auto-create new users
 * 
 * If `create_new_users` is `true`, a new workbench user will be created automatically once
 * a new username is authenticated successfully. These new users can be assigned some roles
 * under `create_new_users_with_roles`. 
 * 
 * If a new user is not assigned any roles, he or she will only have access to resources
 * available for the user roles `exface.Core.ANONYMOUS` and `exface.Core.AUTHENTICATED`.
 * 
 * ## Examples
 * 
 * ### Authentication + create new users with static roles
 * 
 * This configuration will automatically create a workbench user with `SUPERUSER` role
 * if valid credentials for the metamodel's DB connection are provided.
 * 
 * Note, that the `sql_to_get_user_data` query uses the `[#id#]` placeholder, which resolves to the `id` column
 * selected previously by `sql_to_check_password`.
 * 
 * ```
 * {
 * 		"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\SQLAuthenticator",
 * 		"connection_aliases": [
 * 			"my.App.db_connection"
 * 		],
 *      "sql_to_check_password": "SELECT id FROM users_table WHERE user_name = '[#username#]' AND password_hash = PASSWORD('[#password#]')",
 *      "sql_to_get_user_data": "SELECT first_name AS FIRST_NAME, last_name AS LAST_NAME FROM users_table WHERE id = '[#id#]'",
 * 		"create_new_users": true,
 * 		"create_new_users_with_roles": [
 * 			"exface.Core.SUPERUSER"
 * 		]
 * }
 * 
 * ```
 * 
 * ### Sync roles with an external SQL DB
 * 
 * If the external DB is the master for user role assignment, the authenticator can be configured to sync
 * workbench user roles with those in the SQL DB: simply configure a data sheet selecting the names of all
 * roles assigned to a username in `sync_roles_with_data_sheet`.
 * 
 * The role names returned by this data sheet will be matched agains the external roles
 * configuration for this authenticator.
 * 
 * Note, this data sheet also conains placeholders. In contrast to the placeholders in the SQL statements
 * above, these are not the selected columns, but attributes of the `exface.Core.USER` meta object. This is
 * because roles get synchronized only after the user was created and they can be synchronized independently
 * from the password check.
 * 
 * ```
 *  {
 *     "class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\SQLAuthenticator",
 *     "id": "",
 *     "sync_roles_with_data_sheet": {
 *         "object_alias": "my.App.ROLE",
 *		   "columns": [
 *	           {
 *				  "attribute_alias": "Name"
 *			   }
 *			],
 *			"filters": {
 *				"operator": "AND",
 *				"conditions": [
 *					{
 *						"expression": "RELATION_TO__USER_TABLE",
 *						"comparator": "==",
 *						"value": "[#USERNAME#]"
 *					}
 *				]
 *			}
 *		}
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class SQLAuthenticator extends DataConnectionAuthenticator
{    
    private $sqlToCheckPassword = null;
    
    private $sqlToGetUserData = null;
    
    private $connections = [];
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        if (! $token instanceof DataConnectionUsernamePasswordAuthToken) {
            throw new InvalidArgumentException('Invalid token type!');
        }
        $this->checkAuthenticatorDisabledForUsername($token->getUsername());
        
        $user = $this->userExists($token) ? $this->getUserFromToken($token) : null;
        
        try {
            $conn = DataConnectionFactory::createFromModel($this->getWorkbench(), $token->getDataConnectionAlias());
            if (! ($conn instanceof SqlDataConnectorInterface)) {
                throw new InvalidArgumentException('Invalid data connection for SQLAuthenticator: only SQL connections (implementing the SqlDataConnectorInterface) allowed!');
            }
            $sqlAuth = StringDataType::replacePlaceholders($this->getSqlToCheckPassword(), ['username' => $token->getUsername(), 'password' => $token->getPassword()]);
            $queryAuth = $conn->runSql($sqlAuth);
            $authData = $queryAuth->getResultArray();
        } catch (\Throwable $e) {
            throw new AuthenticationFailedError($this, 'Cannot authenticate user in "' . $conn->getName() . '"! ' . $e->getMessage(), null, $e);
        }
        
        if (empty($authData)) {
            throw new AuthenticationFailedError($this, 'Invalid username or password for "' . $conn->getName() . '"!');
        }
        
        if ($user === null) {
            if ($this->getCreateNewUsers() === true) {
                try {
                    $phs = array_merge(['username' => $token->getUsername()], $authData);
                    $sqlUserData = StringDataType::replacePlaceholders($this->getSqlToGetUserData(), $phs);
                    $userDataRows = $conn->runSql($sqlUserData)->getResultArray();
                    if (count($userDataRows) > 1) {
                        throw new UnexpectedValueException('Found multiple users in data source!');
                    }
                    $userData = $userDataRows[0] ?? [];
                } catch (\Throwable $e) {
                    throw new AuthenticationFailedError($this, 'Cannot fetch user data from "' . $conn->getName() . '"! ' . $e->getMessage());
                }
                $user = $this->createUserWithRoles($this->getWorkbench(), $token, $userData);
            } else {            
                throw new AuthenticationFailedError($this, "Authentication failed, no workbench user '{$token->getUsername()}' exists: either create one manually or enable `create_new_users` in authenticator configuration!", '7AL3J9X');
            }
        }
        
        $this->logSuccessfulAuthentication($user, $token->getUsername());
        if ($token->getUsername() !== $user->getUsername()) {
            return new DataConnectionUsernamePasswordAuthToken($token->getDataConnectionAlias(), $user->getUsername(), $token->getPassword());
        }
        
        $this->saveAuthenticatedToken($token);
        
        $this->syncUserRoles($user, $token);
        
        return $token;
    }
    
    /**
     * 
     * @return string
     */
    protected function getSqlToCheckPassword() : string
    {
        return $this->sqlToCheckPassword;
    }
    
    /**
     * SQL statement to test username and passwords provided as placeholders [#username#] and [#password#].
     * 
     * The authentication is successfull if the SQL query returns a non-empty result.
     * 
     * @uxon-property sql_to_check_password
     * @uxon-type string
     * @uxon-required true
     * @uxon-template SELECT id FROM users_table WHERE user_name = '[#username#]' AND password_hash = PASSWORD('[#password#]')
     * 
     * @param string $value
     * @return SQLAuthenticator
     */
    protected function setSqlToCheckPassword(string $value) : SQLAuthenticator
    {
        $this->sqlToCheckPassword = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getSqlToGetUserData() : ?string
    {
        return $this->sqlToGetUserData;
    }
    
    /**
     * SQL statement to get user data via SQL in case `create_new_users` is enabled.
     * 
     * The SQL should return a single row with column names matching attributes of the `exface.Core.USER`
     * meta object. Typically, you would fetch `FIRST_NAME`, `LAST_NAME` and `EMAIL`. You can use the following
     * placeholders in this SQL statement:
     * 
     * - `[#username#]` the username used to log in (same as in `sql_to_check_password`)
     * - any value returned by `sql_to_check_password` - e.g. if you use `SELECT id FROM ...` when
     * checking the password, here you can use [#id#] as placeholder to address exactly the user
     * authenticated.  
     * 
     * @uxon-property sql_to_get_user_data
     * @uxon-type string
     * @uxon-template SELECT first_name AS FIRST_NAME, last_name AS LAST_NAME, email_address AS EMAIL FROM users_table WHERE id = '[#id#]'
     * 
     * @param string $value
     * @return SQLAuthenticator
     */
    protected function setSqlToGetUserData(string $value) : SQLAuthenticator
    {
        $this->sqlToGetUserData = $value;
        return $this;
    }
}