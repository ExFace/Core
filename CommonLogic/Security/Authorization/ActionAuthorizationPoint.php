<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Factories\PermissionFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Model\UiMenuItemInterface;

/**
 * 
 * 
 * @method ActionAuthorizationPolicy[] getPolicies()
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionAuthorizationPoint extends AbstractAuthorizationPoint
{

    /**
     * 
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::authorize()
     */
    public function authorize(ActionInterface $action = null, TaskInterface $task = null, UserImpersonationInterface $userOrToken = null) : ?TaskInterface
    {
        if ($this->isDisabled()) {
            return $task;
        }
        
        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
        
        $page = null;
        if ($action !== null && $action->isDefinedInWidget()) {
            $page = $action->getWidgetDefinedIn()->getPage();
        } elseif ($task !== null && $task->isTriggeredOnPage()) {
            $page = $task->getPageTriggeredOn();
        }
        
        $permissionsGenerator = $this->evaluatePolicies($action, $userOrToken, $page);
        $this->combinePermissions($permissionsGenerator, $userOrToken, $action);
        return $task;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::addPolicy()
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface
    {
        $this->addPolicyInstance(new ActionAuthorizationPolicy($this->getWorkbench(), $name, $effect, $targets, $condition));
        return $this;
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param UserImpersonationInterface $userOrToken
     * @return \Generator
     */
    protected function evaluatePolicies(ActionInterface $action, UserImpersonationInterface $userOrToken, UiMenuItemInterface $menuItem = null) : \Generator
    {
        foreach ($this->getPolicies($userOrToken) as $policy) {
            yield $policy->authorize($userOrToken, $action, $menuItem);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::getResourceName()
     */
    protected function getResourceName($resource) : string
    {
        return "action '{$resource->getAliasWithNamespace()}'";
    }
}