<?php
namespace exface\Core\CommonLogic\Security;

use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Security\SecurityManagerInterface;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\AuthenticationToken\AnonymousAuthToken;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Widgets\LoginPrompt;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Security\AuthenticationToken\RememberMeAuthToken;
use exface\Core\CommonLogic\Security\Authenticators\RememberMeAuthenticator;
use exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Factories\AuthorizationPointFactory;
use exface\Core\CommonLogic\Selectors\AuthorizationPointSelector;
use exface\Core\Interfaces\Selectors\AuthorizationPointSelectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * Default implementation of the SecurityManagerInterface.
 * 
 * @author Andrej Kabachnik
 *
 */
class SecurityManager implements SecurityManagerInterface
{
    private $workbench;
    
    private $authenticators = null;
    
    private $authenticatedToken = null;
    
    private $userCache = [];
    
    private $apCache = [];

    /**
     *
     * @param \exface\Core\CommonLogic\Workbench $exface            
     */
    function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
        
        // Initialize all authenticators to give them the option to register
        // event listeners (e.g. for the exface.Core.Security.OnBeforeAuthentication 
        // event).
        $this->authenticators = self::loadAuthenticatorsFromConfig($this->getWorkbench());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token): AuthenticationTokenInterface
    {      
        $errors = [];
        foreach ($this->getAuthenticators() as $authenticator) {
            if ($authenticator->isSupported($token) === false) {
                continue;
            }
            try {
                $authenticated = $authenticator->authenticate($token);
                $this->storeAuthenticatedToken($authenticated);
                return $authenticated;
            } catch (AuthenticationExceptionInterface $e) {
                $errors[] = $e;
            }
        }
        
        if ($token->isAnonymous() === true) {
            $this->storeAuthenticatedToken($token);
            return $token;
        }
        
        switch (count($errors)) {
            case 0:
                throw new AuthenticationFailedError($this, 'Authentication failed!');
            case 1:
                throw $errors[0];
            default:
                $err = new AuthenticationFailedError($this, 'Authentication failed! Tried ' . count($errors) . ' providers - see log details.' , null, null, $errors);
                throw $err;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        foreach ($this->getAuthenticators() as $authenticator) {
            if ($authenticator->isSupported($token) === true && $authenticator->isAuthenticated($token) === true) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::getAuthenticatedToken()
     */
    public function getAuthenticatedToken() : AuthenticationTokenInterface
    {
        if ($this->authenticatedToken === null) {
            try {
                $token = $this->authenticate(new RememberMeAuthToken());
            } catch (AuthenticationFailedError $e) {
                $token = new AnonymousAuthToken($this->getWorkbench());
            }
            $this->authenticatedToken = $token;
        }
        return $this->authenticatedToken;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::getAuthenticatedUser()
     */
    public function getAuthenticatedUser() : UserInterface
    {
        return $this->getUser($this->getAuthenticatedToken());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::getUser()
     */
    public function getUser(AuthenticationTokenInterface $token) : UserInterface
    {
        if (($user = $this->userCache[$token->getUsername()]) !== null) {
            return $user;    
        }
        
        if ($token->getUsername() && $token->isAnonymous() === false) {
            $user = UserFactory::createFromModel($this->getWorkbench(), $token->getUsername());
        } else {
            $user = UserFactory::createAnonymous($this->getWorkbench());
        }
        $this->userCache[$token->getUsername()] = $user;
        
        return $user;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * @param AuthenticationTokenInterface $token
     * @return self
     */
    protected function storeAuthenticatedToken(AuthenticationTokenInterface $token) : self
    {
        $this->authenticatedToken = $token;
        $this->getWorkbench()->getContext()->getScopeSession()->setSessionAuthToken($token);
        return $this;
    }
    
    /**
     * 
     * @return AuthenticatorInterface[]
     */
    protected function getAuthenticators() : array
    {
        return $this->authenticators;
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @throws UnexpectedValueException
     * @return AuthenticatorInterface[]
     */
    public static function loadAuthenticatorsFromConfig(WorkbenchInterface $workbench) : array
    {
        $authenticators = [];
        $systemConfig = $workbench->getConfig();
        $authenticatorsUxon = $systemConfig->getOption('SECURITY.AUTHENTICATORS');
        $authenticatorsUxonChanged = false;
        foreach ($authenticatorsUxon as $pos => $authConfig) {
            switch (true) {
                case is_string($authConfig):
                    $class = $authConfig;
                    $uxon = null;
                    break;
                case $authConfig instanceof UxonObject:
                    $class = $authConfig->getProperty('class');
                    $uxon = $authConfig->copy()->unsetProperty('class');
                    // Autogenerate ids if the user has forgotten to give one. Ids make sure
                    // authenticators can be addressed even if they are reordered
                    if ($uxon->hasProperty('id') === false) {
                        $newId = strtoupper(StringDataType::convertCaseCamelToUnderscore(StringDataType::substringAfter($class, '\\', $class, false, true)));
                        $suffix = '';
                        foreach ($authenticatorsUxon as $otherConfig) {
                            if ($otherConfig->getProperty('id') === $newId.$suffix) {
                                $suffix = $suffix === '' ? 2 : $suffix+1;
                            }
                        }
                        $authConfig->setProperty('id', $newId.$suffix);
                        $authenticatorsUxon->setProperty($pos, $authConfig);
                        $authenticatorsUxonChanged = true;
                    }
                    break;
                default:
                    throw new UnexpectedValueException('Invalid authenticator configuration in System.config.json: each authenticator can either be a string or an object!');
            }
            
            if ($authenticatorsUxonChanged === true) {
                $systemConfig->setOption('SECURITY.AUTHENTICATORS', $authenticatorsUxon, AppInterface::CONFIG_SCOPE_SYSTEM);
            }
            
            $authenticator = new $class($workbench);
            if ($uxon !== null && $uxon->isEmpty() === false) {
                $authenticator->importUxonObject($uxon);
            }
            $authenticators[] = $authenticator;
        }
        $authenticators[] = new RememberMeAuthenticator($workbench);
        return $authenticators;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getName()
     */
    public function getName(): string
    {
        return 'Security Manager';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token): bool
    {
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets
    {
        if ($container instanceof LoginPrompt) {
            $loginPrompt = $container;
        } else {
            $loginPrompt = WidgetFactory::create($container->getPage(), 'LoginPrompt', $container);
            $container->addWidget($loginPrompt);
        }
        
        foreach ($this->getAuthenticators() as $authenticator) {
            $loginForm = WidgetFactory::create($loginPrompt->getPage(), 'Form', $loginPrompt);
            $loginForm->setObjectAlias('exface.Core.LOGIN_DATA');
            $authenticator->createLoginWidget($loginForm);
            if ($loginForm->isEmpty() === false) {
                $loginForm->setCaption($authenticator->getName());
                $loginPrompt->addForm($loginForm);
            }
        }
        
        return $container;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::getAuthorizationPoint()
     */
    public function getAuthorizationPoint($selectorOrString) : AuthorizationPointInterface
    {
        if (! $ap = $this->apCache[(string) $selectorOrString]) {
            if ($selectorOrString instanceof AuthorizationPointSelectorInterface) {
                $class = $selectorOrString->toString();
                $selector = $selectorOrString;
            } elseif (is_string($selectorOrString)) {
                $class = $selectorOrString;
                $selector = new AuthorizationPointSelector($this->getWorkbench(), $class);
            }
            $ap = AuthorizationPointFactory::createFromSelector($selector);
            $this->apCache[$class] = $ap;
        }
        return $ap;
    }
}