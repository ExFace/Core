<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Exceptions\Security\AccessDeniedError;
use exface\Core\Events\Security\OnAuthorizedEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Exceptions\Security\AccessPermissionDeniedError;

/**
 * 
 * 
 * @method GenericAuthorizationPolicy[] getPolicies()
 * 
 * @author Andrej Kabachnik
 *
 */
class UiPageAuthorizationPoint extends AbstractAuthorizationPoint
{

    /**
     * 
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::authorize()
     */
    public function authorize(UiPageInterface $page = null, UserImpersonationInterface $userOrToken = null) : UiPageInterface
    {
        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
            
        $permission = new CombinedPermission($this->getPolicyCombiningAlgorithm(), $this->evaluatePolicies($page, $userOrToken));
        switch (true) {
            case $permission->isPermitted():
            case ($permission->isIndeterminate() || $permission->isNotApplicable()) && $this->getDefaultPolicyEffect() == PolicyEffectDataType::PERMIT:
                $event = new OnAuthorizedEvent($this, $userOrToken, $page);
                $this->getWorkbench()->eventManager()->dispatch($event);
                return $page;
            case $permission->isDenied():
            case ($permission->isIndeterminate() || $permission->isNotApplicable()) && $this->getDefaultPolicyEffect() == PolicyEffectDataType::DENY:
                if ($page && $userOrToken) {
                    $forUser = $userOrToken->isAnonymous() ? 'for anonymous users' : 'for user "' . $userOrToken->getUsername() . '"';
                    throw new AccessPermissionDeniedError($this, $permission, $userOrToken, $page, 'Access to page "' . $page->getAliasWithNamespace() . '" denied ' . $forUser . '!');
                } else {
                    throw new AccessPermissionDeniedError($this, $permission, $userOrToken, $page, 'Unknown error while validating page access permissions!');
                }
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::addPolicy()
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface
    {
        $this->addPolicyInstance(new GenericAuthorizationPolicy($this->getWorkbench(), $name, $effect, $targets, $condition));
        return $this;
    }
    
    protected function evaluatePolicies(UiPageInterface $page, UserImpersonationInterface $userOrToken) : \Generator
    {
        foreach ($this->getPolicies($userOrToken) as $policy) {
            yield $policy->authorize($userOrToken, $page);
        }
    }
}