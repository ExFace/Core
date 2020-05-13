<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Exceptions\Contexts\ContextAccessDeniedError;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;

/**
 * 
 * 
 * @method GenericAuthorizationPolicy[] getPolicies()
 * 
 * @author Andrej Kabachnik
 *
 */
class ContextAuthorizationPoint extends AbstractAuthorizationPoint
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
    public function authorize(ContextInterface $context = null, UserImpersonationInterface $userOrToken = null) : ContextInterface
    {
        if ($this->isDisabled()) {
            return $context;
        }
        
        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
        
        $permissionsGenerator = $this->evaluatePolicies($context, $userOrToken);
        $this->combinePermissions($permissionsGenerator, $userOrToken, $context);
        return $context;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::addPolicy()
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface
    {
        $this->addPolicyInstance(new ContextAuthorizationPolicy($this->getWorkbench(), $name, $effect, $targets, $condition));
        return $this;
    }
    
    protected function evaluatePolicies(ContextInterface $context, UserImpersonationInterface $userOrToken) : \Generator
    {
        foreach ($this->getPolicies($userOrToken) as $policy) {
            yield $policy->authorize($userOrToken, $context);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::getResourceName()
     */
    protected function getResourceName($resource) : string
    {
        return 'context "' . $resource->getAliasWithNamespace() . '"';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::createAccessDeniedException()
     */
    protected function createAccessDeniedException(string $message, PermissionInterface $permission, UserImpersonationInterface $userOrToken, $resource = null, string $alias = null, \Throwable $previous = null) : AuthorizationExceptionInterface
    {
        return new ContextAccessDeniedError($this, $permission, $userOrToken, $resource, $message, $alias, $previous);
    }
}