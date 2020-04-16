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
        $this->initAuthenticators();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationProviderInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token): AuthenticationTokenInterface
    {      
        $err = new AuthenticationFailedError($this, 'Authentication failed!');
        foreach ($this->getAuthenticators() as $authenticator) {
            if ($authenticator->isSupported($token) === false) {
                continue;
            }
            try {
                $authenticated = $authenticator->authenticate($token);
                $this->storeAuthenticatedToken($authenticated);
                return $authenticated;
            } catch (AuthenticationExceptionInterface $e) {
                $err->addSecondaryError(new AuthenticationFailedError($authenticator, $e->getMessage(), null, $e));
            }
        }
        
        if ($token->isAnonymous() === true) {
            $this->storeAuthenticatedToken($token);
            return $token;
        }
        
        throw $err;
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
     * @return self
     */
    protected function initAuthenticators() : self
    {
        $this->authenticators = [];
        foreach ($this->getWorkbench()->getConfig()->getOption('SECURITY.AUTHENTICATORS') as $authConfig) {
            switch (true) {
                case is_string($authConfig):
                    $class = $authConfig;
                    $uxon = null;
                    break;
                case $authConfig instanceof UxonObject:
                    $class = $authConfig->getProperty('class');
                    $uxon = $authConfig->unsetProperty('class');
                    break;
                default:
                    throw new UnexpectedValueException('Invalid authenticator configuration in System.config.json: each authenticator can either be a string or an object!');
            } 
            $authenticator = new $class($this->getWorkbench());
            if ($uxon !== null && $uxon->isEmpty() === false) {
                $authenticator->importUxonObject($uxon);
            }
            $this->authenticators[] = $authenticator;
        }
        $this->authenticators[] = new RememberMeAuthenticator($this->getWorkbench());
        return $this;
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
}