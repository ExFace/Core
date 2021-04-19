<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\PolicyTargetDataType;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Factories\PermissionFactory;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;

/**
 * Policy to restrict access to workbench contexts.
 * 
 * @author Andrej Kabachnik
 *
 */
class ContextAuthorizationPolicy implements AuthorizationPolicyInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $name = '';
    
    private $userRoleSelector = null;
    
    private $configionUxon = null;
    
    private $effect = null;
    
    private $contextSelector = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $name
     * @param PolicyEffectDataType $effect
     * @param array $targets
     * @param UxonObject $conditionUxon
     */
    public function __construct(WorkbenchInterface $workbench, string $name, PolicyEffectDataType $effect, array $targets, UxonObject $conditionUxon = null)
    {
        $this->workbench = $workbench;
        $this->name = $name;
        if ($role = $targets[PolicyTargetDataType::USER_ROLE]) {
            $this->userRoleSelector = new UserRoleSelector($workbench, $role);
        }
        $this->effect = $effect;
        $this->importUxonObject($conditionUxon);
    }
    
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        return $uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::authorize()
     */
    public function authorize(UserImpersonationInterface $userOrToken = null, ContextInterface $context = null): PermissionInterface
    {
        try {
            if ($userOrToken instanceof AuthenticationTokenInterface) {
                $user = $this->workbench->getSecurity()->getUser($userOrToken);
            } else {
                $user = $userOrToken;
            }
            
            $applied = false;
            
            if ($this->userRoleSelector !== null && $user->hasRole($this->userRoleSelector) === false) {
                return PermissionFactory::createNotApplicable($this);
            } else {
                $applied = true; 
            }
            
            if ($this->getContextSelectorString() !== null) {
                if ($context->getAliasWithNamespace() !== $this->getContextSelectorString()) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this);
            }
        } catch (\Throwable $e) {
            $context->getWorkbench()->getLogger()->logException($e);
            return PermissionFactory::createIndeterminate($e, $this->getEffect(), $this);
        }
        
        // If all targets are applicable, the permission is the effect of this condition.
        return PermissionFactory::createFromPolicyEffect($this->getEffect(), $this);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::getEffect()
     */
    public function getEffect() : PolicyEffectDataType
    {
        return $this->effect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::getName()
     */
    public function getName() : ?string
    {
        return $this->name;
    }
    
    /**
     *
     * @return string
     */
    protected function getContextSelectorString() : ?string
    {
        return $this->contextSelector;
    }
    
    /**
     * Alias of the context this policy applies to - e.g. `exface.Core.DebugContext`.
     * 
     * @uxon-property context
     * @uxon-type metamodel:context
     * 
     * @param string $value
     * @return ContextAuthorizationPolicy
     */
    protected function setContext(string $value) : ContextAuthorizationPolicy
    {
        $this->contextSelector = $value;
        return $this;
    }
}