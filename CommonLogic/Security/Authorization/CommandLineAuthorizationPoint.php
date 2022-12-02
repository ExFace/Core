<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Events\Facades\OnHttpRequestHandlingEvent;
use exface\Core\Events\Facades\OnCliCommandReceivedEvent;

/**
 * Manages access to facades
 * 
 * @method CommandLineAuthorizationPolicy[] getPolicies()
 * 
 * @author Andrej Kabachnik
 *
 */
class CommandLineAuthorizationPoint extends AbstractAuthorizationPoint
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::register()
     */
    protected function register() : AuthorizationPointInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnCliCommandReceivedEvent::getEventName(), [$this, 'authorizeEvent']);
        return $this;
    }
        
    /**
     * Checks authorization for an exface.Core.Facades.OnFacadeInit event.
     * @param OnHttpRequestHandlingEvent $event
     * @return void
     */
    public function authorizeEvent(OnCliCommandReceivedEvent $event)
    {
        $authToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        $this->authorize($event->getFacade(), $event->getCliCommand(), $authToken);
        return;
    }
    
    /**
     * 
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::authorize()
     */
    public function authorize(FacadeInterface $facade = null, string $command = null, UserImpersonationInterface $userOrToken = null) : FacadeInterface
    {
        if ($this->isDisabled()) {
            return $facade;
        }
        
        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
        
        $permissionsGenerator = $this->evaluatePolicies($facade, $command, $userOrToken);
        $this->combinePermissions($permissionsGenerator, $userOrToken, $command);
        return $facade;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::addPolicy()
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface
    {
        $this->addPolicyInstance(new CommandLineAuthorizationPolicy($this->getWorkbench(), $name, $effect, $targets, $condition));
        return $this;
    }
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param UserImpersonationInterface $userOrToken
     * @return \Generator
     */
    protected function evaluatePolicies(FacadeInterface $facade, string $command, UserImpersonationInterface $userOrToken) : \Generator
    {
        foreach ($this->getPolicies($userOrToken) as $policy) {
            yield $policy->authorize($userOrToken, $facade, $command);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::getResourceName()
     */
    protected function getResourceName($resource) : string
    {
        return "CLI command \"{$resource}\"";
    }
}