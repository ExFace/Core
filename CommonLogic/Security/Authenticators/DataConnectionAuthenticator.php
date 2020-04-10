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
use exface\Core\CommonLogic\Security\Authenticators\Traits\CreateUserFromTokenTrait;

/**
 * Performs authentication via data connectors. 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataConnectionAuthenticator extends AbstractAuthenticator
{    
    use CreateUserFromTokenTrait;
    
    private $authenticatedToken = null;
    
    private $connectionAliases = null;
    
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
        } catch (AuthenticationException $e) {
            throw new AuthenticationFailedError($this, $e->getMessage(), null, $e);
        }
        if ($this->getCreateNewUsers() === true) {
            $user = $this->createUserWithRoles($this->getWorkbench(), $token);            
            //second authentification to save credentials
            $connector->authenticate($token, true, $user);
        } else {
            if (empty($this->getUserData($this->getWorkbench(), $token)->getRows())) {
                throw new AuthenticationFailedError($this, 'Authentication failed, no PowerUI user with that username exists and none was created!');
            }
        }
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
                'caption' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.DATACONNECTION.CONNECTION'),
                'selectable_options' => array_combine($this->getConnectionAliases(), $conNames) ?? [],
                'required' => true
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