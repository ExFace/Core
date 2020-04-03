<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\CommonLogic\Security\AuthenticationToken\DataConnectionUsernamePasswordAuthToken;
use exface\Core\Factories\DataConnectionFactory;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\CommonLogic\Security\Authenticators\Traits\createUserFromTokenTrait;

/**
 * Performs authentication via data connectors. 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataConnectionAuthenticator extends AbstractAuthenticator
{    
    use createUserFromTokenTrait;
    
    private $authenticatedToken = null;
    
    private $connectionAliases = null;
    
    private $createNewUsers = false;
    
    private $newUsersRoles = null;
    
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
        
        try {
            $connector = DataConnectionFactory::createFromModel($this->getWorkbench(), $token->getDataConnectionAlias());;
            $connector->authenticate($token, false);
            $this->authenticatedToken = $token;
        } catch (AuthenticationException $e) {
            throw new AuthenticationFailedError($this, $e->getMessage(), null, $e);
        }
        $userDataSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER');
        $userDataSheet->getFilters()->addConditionFromString('USERNAME', $token->getUsername(), ComparatorDataType::EQUALS);
        $userDataSheet->dataRead();
        if (empty($userDataSheet->getRows())) {
            $user = $this->createUserFromToken($token, $this->getWorkbench());
            if ($this->getNewUserRoles() !== null) {
                $user = $this->addRolesToUser($this->getWorkbench(), $user, $this->getNewUserRoles());
            }
        }
        //second authentification to save credentials
        $connector->authenticate($token, true, $user);
        $this->authenticatedToken = $token;
        return $token;
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
        return $token instanceof DataConnectionUsernamePasswordAuthToken;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        //return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.LDAP.SIGN_IN');
        return 'DataConnectionAuthenticator';
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
     * Set if a new PowerUI user should be created if no user with that name already exists.
     * 
     * @uxon-property create_new_users
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return DataConnectionAuthenticator
     */
    public function setCreateNewUsers(bool $trueOrFalse) : DataConnectionAuthenticator
    {
        $this->createNewUsers = $trueOrFalse;
        return $this;
    }
    
    protected function getCreateNewUsers() : bool
    {
        return $this->createNewUsers;
    }
    
    /**
     * The role aliases for the roles newly created users should inherit.
     *
     * @uxon-property create_new_users_with_roles
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $create_new_users_with_roles
     * @return DataConnectionAuthenticator
     */
    public function setCreateNewUsersWithRoles($arrayOrUxon) : DataConnectionAuthenticator
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->newUsersRoles = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->newUsersRoles = $arrayOrUxon;
        }
        return $this;
    }
    
    /**
     * 
     * @return array|NULL
     */
    protected function getNewUserRoles() : ?array
    {
        return $this->newUsersRoles;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\SymfonyAuthenticator::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets
    {   
        $conNames = [];
        if ($this->getConnectionAliases() !== null) {
            foreach($this->getConnectionAliases() as $alias) {
                $connector = DataConnectionFactory::createFromModel($this->getWorkbench(), $alias);
                $conNames[] = $connector->getName();
            }
        }
        
        $container->setWidgets(new UxonObject([
            [
                'data_column_name' => 'DATACONNECTIONALIAS',
                'widget_type' => 'InputSelect',
                'caption' => 'DATA CONNECTION',
                'selectable_options' => array_combine($this->getConnectionAliases(), $conNames) ?? [],
                'required' => true
            ],[
                'attribute_alias' => 'USERNAME',
                'caption' => 'USERNAME',
                'required' => true
            ],[
                'attribute_alias' => 'PASSWORD'
            ],[
                'attribute_alias' => 'AUTH_TOKEN_CLASS',
                'value' => '\\' . DataConnectionUsernamePasswordAuthToken::class,
                'widget_type' => 'InputHidden'
            ]
        ]));
        
        if ($container instanceof iLayoutWidgets) {
            $container->setColumnsInGrid(1);
        }
        
        if ($container instanceof iHaveButtons && $container->hasButtons() === false) {
            $container->addButton($container->createButton(new UxonObject([
                'action_alias' => 'exface.Core.Login',
                'align' => EXF_ALIGN_OPPOSITE,
                'visibility' => WidgetVisibilityDataType::PROMOTED
            ])));
        }
        
        return $container;
    }
}