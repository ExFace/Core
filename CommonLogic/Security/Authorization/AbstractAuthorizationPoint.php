<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\Factories\PermissionFactory;
use exface\Core\Interfaces\UserImpersonationInterface;

abstract class AbstractAuthorizationPoint implements AuthorizationPointInterface
{
    
    private $workbench = null;
    
    private $app = null;
    
    private $alias = null;
    
    private $policies = null;
    
    private $active = true;
    
    private $combinationAlgorithm = null;
    
    private $defaultEffect = null;
    
    private $name = null;
    
    private $isLoadedForUser = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(AppInterface $app, string $alias)
    {
        $this->workbench = $app->getWorkbench();
        $this->alias = $alias;
        $this->app = $app;
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
     * @return AuthorizationPolicyInterface[]
     */
    protected function getPolicies() : array
    {
        return $this->policies;
    }
    
    protected function addPolicyInstance(AuthorizationPolicyInterface $policy) : AbstractAuthorizationPoint
    {
        $this->policies[] = $policy;
        return $this;
    }
    
    public function getNamespace()
    {
        return $this->getApp()->getAliasWithNamespace();
    }
    
    public function getAlias()
    {
        return $this->alias;
    }
    
    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
    }
    
    public function setActive(bool $trueOrFalse): AuthorizationPointInterface
    {
        $this->active = $trueOrFalse;
        return $this;
    }
    
    protected function isActive(): bool
    {
        return $this->active;
    }
    
    public function setPolicyCombiningAlgorithm(PolicyCombiningAlgorithmDataType $algorithm): AuthorizationPointInterface
    {
        $this->combinationAlgorithm = $algorithm;
        return $this;
    }
    
    protected function getPolicyCombiningAlgorithm() : PolicyCombiningAlgorithmDataType
    {
        return $this->combinationAlgorithm;
    }
    
    public function setDefaultPolicyEffect(PolicyEffectDataType $effect): AuthorizationPointInterface
    {
        $this->defaultEffect = $effect;
        return $this;
    }
    
    protected function getDefaultPolicyEffect() : PolicyEffectDataType
    {
        return $this->defaultEffect;
    }
    
    public function getApp(): AppInterface
    {
        return $this->app;
    }
    
    /**
     * 
     * @param \Generator $permissions
     * @return PermissionInterface
     */
    protected function combinePermissions(iterable $permissions) : PermissionInterface
    {
        switch ($this->getPolicyCombiningAlgorithm()->toString()) {
            case PolicyCombiningAlgorithmDataType::DENY_UNLESS_PERMIT:
                foreach ($permissions as $permission) {
                    if ($permission->isPermitted()) {
                        return $permission;
                    }
                }
                return PermissionFactory::createDenied();
            break;
            case PolicyCombiningAlgorithmDataType::PERMIT_UNLESS_DENY:
                foreach ($permissions as $permission) {
                    if ($permission->isDenied()) {
                        return $permission;
                    }
                }
                return PermissionFactory::createPermitted();
            break;
        }
        
    }
    
    /**
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }
    
    /**
     * 
     * @param string $value
     * @return AuthorizationPointInterface
     */
    public function setName(string $value) : AuthorizationPointInterface
    {
        $this->name = $value;
        return $this;
    }
    
    protected function isLoaded() : bool
    {
        return $this->isLoadedForUser !== null;
    }
    
    protected function loadModel(UserImpersonationInterface $userOrToken) : self
    {
        $this->policies = [];
        $this->isLoadedForUser = null;
        $this->workbench->model()->getModelLoader()->loadAuthorizationPoint($this, $userOrToken);
        $this->isLoadedForUser = $userOrToken;
        return $this;
    }
}