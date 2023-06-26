<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\CommonLogic\Security\AuthenticationToken\DataConnectionUsernamePasswordAuthToken;
use exface\Core\Factories\DataConnectionFactory;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use exface\Core\CommonLogic\Security\Authenticators\Traits\CreateUserFromTokenTrait;
use exface\Core\Exceptions\Security\AuthenticatorConfigError;
use exface\Core\Widgets\Form;

/**
 * Performs authentication via selected data connections.
 * 
 * Allows single-sign-on with web services, databases, etc. - anything, that can be
 * connected to via data connectors. The user is authenticated, if he or she can provide 
 * valid credentials for a selected data connection.
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
 * 		"class": "\\exface\\Core\\CommonLogic\\Security\\Authenticators\\DataConnectionAuthenticator",
 * 		"connection_aliases": [
 * 			"exface.Core.METAMODEL_CONNECTION"
 * 		],
 * 		"create_new_users": true,
 * 		"create_new_users_with_roles": [
 * 			"exface.Core.SUPERUSER"
 * 		]
 * }
 * 
 * ```
 * 
 * If you specify multiple connections, the user will be able to choose one berfor logging in.
 * For a single connection you can also hide the connection selector completely by setting
 * `hide_connection_selector` to `true`. This way the user will not even see, which data source
 * he or she is going to authenticate agains.
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
class DataConnectionAuthenticator extends AbstractAuthenticator
{    
    use CreateUserFromTokenTrait;
    
    private $authenticatedToken = null;
    
    private $connectionAliases = null;
    
    private $hideConnectionSelector = false;
    
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
            $connector = DataConnectionFactory::createFromModel($this->getWorkbench(), $token->getDataConnectionAlias());;
            if ($user === null) {
                $authenticatedToken = $connector->authenticate($token, false);
            } else {
                $authenticatedToken = $connector->authenticate($token, true, $user, true);
            }
        } catch (AuthenticationException $e) {
            throw new AuthenticationFailedError($this, $e->getMessage(), null, $e);
        }
        
        if (! $authenticatedToken->isAnonymous()) {
            if ($user === null) {
                if ($this->getCreateNewUsers() === true) {
                    $user = $this->createUserWithRoles($this->getWorkbench(), $token);            
                    // second authentification to save credentials
                    $connector->authenticate($token, true, $user, true);
                } else {            
                    throw new AuthenticationFailedError($this, "Authentication failed, no workbench user '{$token->getUsername()}' exists: either create one manually or enable `create_new_users` in authenticator configuration!", '7AL3J9X');
                }
            }
            
            $this->logSuccessfulAuthentication($user, $token->getUsername());
            if ($token->getUsername() !== $user->getUsername()) {
                return new DataConnectionUsernamePasswordAuthToken($token->getDataConnectionAlias(), $user->getUsername(), $token->getPassword());
            }
        }
        
        $this->saveAuthenticatedToken($authenticatedToken);
        
        $this->syncUserRoles($user, $authenticatedToken);
        
        return $authenticatedToken;
    }
    
    /**
     * 
     * @param AuthenticationTokenInterface $token
     * @return DataConnectionAuthenticator
     */
    protected function saveAuthenticatedToken(AuthenticationTokenInterface $token) : DataConnectionAuthenticator
    {
        $this->authenticatedToken = $token;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        return $token === $this->authenticatedToken;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool
    {
        if (! $token instanceof DataConnectionUsernamePasswordAuthToken) {
            return false;
        }
        
        if (! in_array($token->getDataConnectionAlias(), ($this->getConnectionAliases() ?? []))) {
            return false;
        }
        
        if (! $this->isSupportedFacade($token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.DATACONNECTION.SIGN_IN');
    }
    
    /**
     * The connection aliases for connections that should be chooseable to login.
     *
     * @uxon-property connection_aliases
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $connection_aliases
     * @return DataConnectionAuthenticator
     */
    public function setConnectionAliases($arrayOrUxon) : DataConnectionAuthenticator
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->connectionAliases = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->connectionAliases = $arrayOrUxon;
        }
        return $this;
    }
    
    /**
     *
     * @return string[]|NULL
     */
    protected function getConnectionAliases() : ?array
    {
        return $this->connectionAliases;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::createLoginForm()
     */
    protected function createLoginForm(Form $emptyForm) : Form
    {   
        $conAliases = $this->getConnectionAliases();
        $conNames = [];
        if ($conAliases !== null) {
            foreach($conAliases as $alias) {
                $connector = DataConnectionFactory::createFromModel($this->getWorkbench(), $alias);
                $conNames[] = $connector->getName();
            }
        }        
        
        $hideSelector = $this->getHideConnectionSelector();
        if ($hideSelector && count($conAliases) > 1) {
            throw new AuthenticatorConfigError($this, 'Cannot hide data connection selector in authenticator "' . $this->getName() . '": multiple connections specified!');
        }
        
        $emptyForm->setWidgets(new UxonObject([
            [
                'data_column_name' => 'DATACONNECTIONALIAS',
                'widget_type' => 'InputSelect',
                'caption' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.DATACONNECTION.CONNECTION'),
                'selectable_options' => array_combine($conAliases, $conNames) ?? [],
                'required' => true,
                'value' => count($conAliases) === 1 ? $conAliases[0] : null,
                'hidden' => $this->getHideConnectionSelector()
            ],[
                'attribute_alias' => 'USERNAME',
                'caption' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.DATACONNECTION.USERNAME'),
                'required' => true
            ],[
                'attribute_alias' => 'PASSWORD'
            ],[
                'attribute_alias' => 'AUTH_TOKEN_CLASS',
                'value' => '\\' . DataConnectionUsernamePasswordAuthToken::class,
                'widget_type' => 'InputHidden'
            ]
        ]));
        
        $emptyForm->setColumnsInGrid(1);
        
        if ($emptyForm->hasButtons() === false) {
            $emptyForm->addButton($emptyForm->createButton(new UxonObject([
                'action_alias' => 'exface.Core.Login',
                'align' => EXF_ALIGN_OPPOSITE,
                'visibility' => WidgetVisibilityDataType::PROMOTED
            ])));
        }
        
        return $emptyForm;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getHideConnectionSelector() : bool
    {
        return $this->hideConnectionSelector;
    }
    
    /**
     * Set to TRUE to hide the widget that allows the user to select a connection on the login promt.
     * 
     * @uxon-property hide_connection_selector
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return DataConnectionAuthenticator
     */
    protected function setHideConnectionSelector(bool $value) : DataConnectionAuthenticator
    {
        $this->hideConnectionSelector = $value;
        return $this;
    }
}