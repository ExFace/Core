<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Events\Facades\OnHttpRequestHandlingEvent;
use exface\Core\Events\Facades\OnCliCommandReceivedEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;

/**
 * Manages access to facades
 * 
 * TODO split into two authorization points: for CLI facades and HTTP facades separately and allow to
 * define policies for specific URLs and commands
 * 
 * @method FacadeAuthorizationPolicy[] getPolicies()
 * 
 * @author Andrej Kabachnik
 *
 */
class FacadeAuthorizationPoint extends AbstractAuthorizationPoint
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::register()
     */
    protected function register() : AuthorizationPointInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnHttpRequestHandlingEvent::getEventName(), [$this, 'authorizeEvent']);
        $this->getWorkbench()->eventManager()->addListener(OnCliCommandReceivedEvent::getEventName(), [$this, 'authorizeEvent']);
        return $this;
    }
        
    /**
     * Checks authorization for an exface.Core.Facades.OnFacadeInit event.
     * @param OnHttpRequestHandlingEvent $event
     * @return void
     */
    public function authorizeEvent(FacadeEventInterface $event)
    {
        $authToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        $this->authorize($event->getFacade(), $authToken);
        return;
    }
    
    /**
     * 
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::authorize()
     */
    public function authorize(FacadeInterface $facade = null, UserImpersonationInterface $userOrToken = null) : FacadeInterface
    {
        if ($this->isDisabled()) {
            return $facade;
        }
        
        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
        
        $permissionsGenerator = $this->evaluatePolicies($facade, $userOrToken);
        $this->evaluatePermissions($permissionsGenerator, $userOrToken, $facade);
        return $facade;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::addPolicy()
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface
    {
        $this->addPolicyInstance(new FacadeAuthorizationPolicy($this->getWorkbench(), $name, $effect, $targets, $condition));
        return $this;
    }
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param UserImpersonationInterface $userOrToken
     * @return \Generator
     */
    protected function evaluatePolicies(FacadeInterface $facade, UserImpersonationInterface $userOrToken) : \Generator
    {
        foreach ($this->getPolicies($userOrToken) as $policy) {
            yield $policy->authorize($userOrToken, $facade);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::getResourceName()
     */
    protected function getResourceName($resource) : string
    {
        return "facade \"{$resource->getAliasWithNamespace()}\"";
    }
}