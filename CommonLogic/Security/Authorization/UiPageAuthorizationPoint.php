<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Events\Security\OnAuthorizedEvent;

/**
 * Authorizes access to UI pages and menu tree items.
 * 
 * In addition to the regular behavior of authorization points, this one will grant
 * access if the regular decision is `not applicable` or `indeterminate` in case
 * the page was created by the user requesting access. This makes sure, users have
 * access to a new page immediately after creating it. It also ensures, that a user
 * "sees" his own unpublished pages no matter what policies apply to his or her
 * account.
 * 
 * @method UiPageAuthorizationPolicy[] getPolicies()
 * 
 * @author Andrej Kabachnik
 *
 */
class UiPageAuthorizationPoint extends AbstractAuthorizationPoint
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::register()
     */
    protected function register() : AuthorizationPointInterface
    {
        return $this;
    }
    
    /**
     * 
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::authorize()
     */
    public function authorize(UiMenuItemInterface $pageOrMenuNode = null, UserImpersonationInterface $userOrToken = null) : UiMenuItemInterface
    {
        if ($this->isDisabled()) {
            return $pageOrMenuNode;
        }
        
        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
        
        $permissionsGenerator = $this->evaluatePolicies($pageOrMenuNode, $userOrToken);
        $this->combinePermissions($permissionsGenerator, $userOrToken, $pageOrMenuNode);
        return $pageOrMenuNode;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::combinePermissions()
     */
    protected function combinePermissions(iterable $permissions, UserImpersonationInterface $userOrToken, $resource = null) : CombinedPermission
    {
        try {
            $decision = parent::combinePermissions($permissions, $userOrToken, $resource);
        } catch (AuthorizationExceptionInterface $e) {
            // If the decision in "not applicable", see if the current user is the creator of the
            // page or menu item. If so, suppress the exception thus giving access.
            if ($resource instanceof UiMenuItemInterface) {
                if (! $decision) {
                    $decision = $e->getPermission();
                }
                if ($resource->isPublished() === false && ($decision->isNotApplicable() || $decision->isIndeterminate())) {
                    if ($userOrToken instanceof AuthenticationTokenInterface) {
                        $user = $this->getWorkbench()->getSecurity()->getUser($userOrToken);
                    } else {
                        $user = $userOrToken;
                    }
                    $creatorSelector = $resource->getCreatedByUserSelector();
                    switch (true) {
                        case $creatorSelector->isUid() && $creatorSelector->toString() === $user->getUid():
                        case $creatorSelector->isUsername() && $creatorSelector->toString() === $user->getUsername():
                            $event = new OnAuthorizedEvent($this, $decision, $userOrToken, $resource);
                            $this->workbench->getLogger()->debug('Authorized ' . $this->getResourceName($resource), [], $event);
                            $this->getWorkbench()->eventManager()->dispatch($event);
                            return $decision;
                    }
                }
            }
            throw $e;
        }
        return $decision;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::addPolicy()
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface
    {
        $this->addPolicyInstance(new UiPageAuthorizationPolicy($this->getWorkbench(), $name, $effect, $targets, $condition));
        return $this;
    }
    
    /**
     * 
     * @param UiPageInterface $pageOrMenuNode
     * @param UserImpersonationInterface $userOrToken
     * @return \Generator
     */
    protected function evaluatePolicies(UiMenuItemInterface $pageOrMenuNode, UserImpersonationInterface $userOrToken) : \Generator
    {
        foreach ($this->getPolicies($userOrToken) as $policy) {
            yield $policy->authorize($userOrToken, $pageOrMenuNode);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::getResourceName()
     */
    protected function getResourceName($resource) : string
    {
        return 'page "' . $resource->getName() . '" (alias ' . $resource->getAliasWithNamespace() . ')';
    }
}