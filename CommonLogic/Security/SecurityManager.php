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
use exface\Core\Interfaces\Exceptions\AuthenticationExceptionInterface;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\CommonLogic\Selectors\AuthorizationPointSelector;
use exface\Core\Interfaces\Selectors\AuthorizationPointSelectorInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\SecurityException;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\Events\Security\OnAuthenticatedEvent;
use exface\Core\Exceptions\Security\AuthenticationFailedMultiError;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;

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
    
    private $authPoints = [];

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
        
        // Initialize authorization points if the workbench is already installed.
        // If it's not installed, we are in the process of installation and obviously no
        // authorization restrictions are needed.
        if ($workbench->isInstalled()) {
            foreach ($workbench->model()->getModelLoader()->loadAuthorizationPoints() as $ap) {
                $this->authPoints[get_class($ap)] = $ap; 
            }
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::authenticate()
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
                $event = new OnAuthenticatedEvent($this->getWorkbench(), $authenticated, $authenticator);
                $this->getWorkbench()->eventManager()->dispatch($event);
                return $authenticated;
            } catch (AuthenticationExceptionInterface $e) {
                $errors[] = $e;
            }
        }
        
        if ($token->isAnonymous() === true) {
            $event = new OnAuthenticatedEvent($this->getWorkbench(), $token);
            $this->getWorkbench()->eventManager()->dispatch($event);
            $this->storeAuthenticatedToken($token);
            return $token;
        }
        
        switch (count($errors)) {
            case 0:
                throw new AuthenticationFailedError($this, 'Authentication failed!');
            case 1:
                throw $errors[0];
            default:
                $err = new AuthenticationFailedMultiError($this, 'Authentication failed! Tried ' . count($errors) . ' providers - see log details.' , null, $errors);
                foreach ($errors as $e) {
                    $this->getWorkbench()->getLogger()->logException($e);
                }
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
        if (($user = ($this->userCache[$token->getUsername()] ?? null)) !== null) {
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
        //$authenticators[] = new RememberMeAuthenticator($workbench);
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
            try {
                $authenticator->createLoginWidget($loginForm);
            } catch (\Throwable $e) {
                if (! ($e instanceof ExceptionInterface)) {
                    $e = new InternalError($e->getMessage(), null, $e);
                }
                $loginForm->addWidget(WidgetFactory::createFromUxonInParent($loginForm, new UxonObject([
                    'widget_type' => 'Message',
                    'type' => 'error',
                    'text' => 'Failed to initialize authenticator "' . $authenticator->getName() . '": see log ID "' . $e->getId() . '" for details!'
                ])));
                $this->getWorkbench()->getLogger()->logException($e);
            }
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
        if (! $ap = $this->authPoints[(string) $selectorOrString]) {
            if (! $selectorOrString instanceof AuthorizationPointSelectorInterface) {
                $selector = new AuthorizationPointSelector($this->getWorkbench(), $selectorOrString);
            } else {
                $selector = $selectorOrString;
            }
            
            if ($selector->isClassname()) {
                foreach ($this->authPoints as $cached) {
                    if (ltrim($selector->toString(), "\\") === get_class($cached)) {
                        $ap = $cached;
                        $this->authPoints[(string) $selectorOrString] = $cached;
                        break;
                    }
                }
            } elseif ($selector->isFilepath()) {
                $selectorClass = PhpFilePathDataType::findClassInFile($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $selector->toString());
                foreach ($this->authPoints as $cached) {
                    if (ltrim($selectorClass, "\\") === get_class($cached)) {
                        $ap = $cached;
                        $this->authPoints[(string) $selectorOrString] = $cached;
                        break;
                    }
                }
            }
        }
        
        if (! $ap) {
            if (! $this->getWorkbench()->isInstalled()) {
                throw new SecurityException('Cannot initialize security system: the workbench is not installed correctly!', '7DUPQZE');
            } else {
                throw new SecurityException('Authorization point "' . (string) $selectorOrString . '" not found!');
            }
        }
        
        return $ap;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::getTokenLifetime()
     */
    public function getTokenLifetime(AuthenticationTokenInterface $token): ?int
    {
        return null;
    }
}