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
 * ## Examples
 * 
 * ### Authentication + create new users with static roles
 * 
 * This configuration will automatically create a workbench user with `SUPERUSER` role
 * if valid credentials for the metamodel's DB connection are provided.
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
 * If you specify multiple connections, the user will be able to choose one berfor logging in.
 * 
 * If `create_new_users` is `true`, a new workbench user will be created automatically once
 * a new username is authenticated successfully. These new users can be assigned some roles
 * under `create_new_users_with_roles`. 
 * 
 * If a new user is not assigned any roles, he or she will only have access to resources
 * available for the user roles `exface.Core.ANONYMOUS` and `exface.Core.AUTHENTICATED`.
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