<?php
namespace exface\Core\CommonLogic\Security\Authenticators;

use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;
use exface\Core\Exceptions\RuntimeException;

/**
 * Provides common base function for authenticators.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractAuthenticator implements AuthenticatorInterface, iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;
    
    use TranslatablePropertyTrait;
    
    private $workbench = null;
    
    private $name = null;
    
    private $id = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
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
    
    protected function getSymfonyAuthManager() : AuthenticationProviderManager
    {
        if ($this->symfonyAuthManager === null) {
            $this->symfonyAuthManager = new AuthenticationProviderManager($this->getSymfonyAuthProviders());
            if ($this->getWorkbench()->eventManager() instanceof EventDispatcherInterface) {
                $this->symfonyAuthManager->setEventDispatcher($this->getWorkbench()->eventManager()->getSymfonyEventDispatcher());
            }
        }
        return $this->symfonyAuthManager;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container) : iContainOtherWidgets
    {
        return $container;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject([
            'name' => $this->getName()
        ]);
    }
    
    /**
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name !== null ? $this->evaluatePropertyExpression($this->name) : $this->getNameDefault();
    }
    
    /**
     * The name of the authentication method will be shown on the login-screen and in error messages and traces.
     * 
     * Use the `=TRANSLATE()` formula to make the name translatable.
     * 
     * @uxon-property name
     * @uxon-type string     * 
     * 
     * @param string $value
     * @return AbstractAuthenticator
     */
    protected function setName(string $value) : AbstractAuthenticator
    {
        $this->name = $value;
        return $this;
    }
    
    /**
     * Returns the default name of the authenticator (if no name was set in it's configuration).
     * 
     * @return string
     */
    abstract protected function getNameDefault() : string;
    
    protected function getId() : string
    {
        if ($this->id === null) {
            throw new RuntimeException('Missing "id" in authenticator configuration!');
        }
        return $this->id;
    }
    
    /**
     * Unique identifier for this authenticator configuration.
     * 
     * Each item in the config option `SECURITY.AUTHENTICATORS` must have a unique id!
     * 
     * @uxon-property id
     * @uxon-type string
     * 
     * @param string $id
     * @return AbstractAuthenticator
     */
    protected function setId(string $id) : AbstractAuthenticator
    {
        $this->id = $id;
        return $this;
    }
}