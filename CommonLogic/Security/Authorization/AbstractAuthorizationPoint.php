<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Exceptions\Security\AccessPermissionDeniedError;
use exface\Core\Events\Security\OnAuthorizedEvent;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;

/**
 * Base class for core authorization points.
 * 
 * @author Andrej Kabachnik
 *
 */
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
    
    private $uid = null;
    
    private $isLoadedForUser = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(AppInterface $app)
    {
        $this->workbench = $app->getWorkbench();
        $this->app = $app;
        $this->register();
    }
    
    protected abstract function register() : AuthorizationPointInterface;
    
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
    public function getPolicies(UserImpersonationInterface $userOrToken) : array
    {
        if ($this->isPolicyModelLoaded($userOrToken) === false) {
            $this->loadPolicies($userOrToken);
        }
        
        return $this->policies;
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return AbstractAuthorizationPoint
     */
    protected function addPolicyInstance(AuthorizationPolicyInterface $policy) : AbstractAuthorizationPoint
    {
        $this->policies[] = $policy;
        return $this;
    }
   
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::setDisabled()
     */
    public function setDisabled(bool $trueOrFalse): AuthorizationPointInterface
    {
        $this->active = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::isDisabled()
     */
    public function isDisabled(): bool
    {
        return $this->active;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::setPolicyCombiningAlgorithm()
     */
    public function setPolicyCombiningAlgorithm(PolicyCombiningAlgorithmDataType $algorithm): AuthorizationPointInterface
    {
        $this->combinationAlgorithm = $algorithm;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::getPolicyCombiningAlgorithm()
     */
    public function getPolicyCombiningAlgorithm() : PolicyCombiningAlgorithmDataType
    {
        return $this->combinationAlgorithm;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::setDefaultPolicyEffect()
     */
    public function setDefaultPolicyEffect(PolicyEffectDataType $effect): AuthorizationPointInterface
    {
        $this->defaultEffect = $effect;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::getDefaultPolicyEffect()
     */
    public function getDefaultPolicyEffect() : PolicyEffectDataType
    {
        return $this->defaultEffect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::getApp()
     */
    public function getApp(): AppInterface
    {
        return $this->app;
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::getUid()
     */
    public function getUid() : string
    {
        return $this->uid;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::setUid()
     */
    public function setUid(string $value) : AuthorizationPointInterface
    {
        $this->uid = $value;
        return $this;
    }
    
    /**
     * 
     * @param UserImpersonationInterface $userOrToken
     * @return bool
     */
    protected function isPolicyModelLoaded(UserImpersonationInterface $userOrToken) : bool
    {
        return $this->isLoadedForUser !== null && $userOrToken->getUsername() === $this->isLoadedForUser->getUsername();
    }
    
    /**
     * 
     * @param UserImpersonationInterface $userOrToken
     * @return self
     */
    protected function loadPolicies(UserImpersonationInterface $userOrToken) : self
    {
        $this->policies = [];
        $this->isLoadedForUser = null;
        $this->workbench->model()->getModelLoader()->loadAuthorizationPolicies($this, $userOrToken);
        $this->isLoadedForUser = $userOrToken;
        return $this;
    }
    
    /**
     * Combines the given permissions triggering the `OnAuthorizedEvent` or throwing access-denied errors
     * depending on the configuration of the authorization point - return the resulting combined permission.
     * 
     * The parameters $userOrToken and $resource are only used in the events/exceptions.
     * 
     * Returning the resulting `CombinedPermission` allows to appen additional validation logic even if
     * the current configuration of the authorization point allows access.
     * 
     * @param PermissionInterface[] $permissions
     * @param UserImpersonationInterface $userOrToken
     * @param mixed $resource
     * 
     * @triggers \exface\Core\Events\Security\OnAuthorizedEvent
     * 
     * @throws AuthorizationExceptionInterface
     * 
     * @return CombinedPermission
     */
    protected function combinePermissions(iterable $permissions, UserImpersonationInterface $userOrToken, $resource = null) : CombinedPermission
    {
        $permission = new CombinedPermission($this->getPolicyCombiningAlgorithm(), $permissions);
        switch (true) {
            case $permission->isPermitted():
            case ($permission->isIndeterminate() || $permission->isNotApplicable()) && $this->getDefaultPolicyEffect() == PolicyEffectDataType::PERMIT:
                $event = new OnAuthorizedEvent($this, $userOrToken, $resource);
                $this->getWorkbench()->eventManager()->dispatch($event);
                break;
            case $permission->isDenied():
            case ($permission->isIndeterminate() || $permission->isNotApplicable()) && $this->getDefaultPolicyEffect() == PolicyEffectDataType::DENY:
                if ($resource && $userOrToken) {
                    $forUser = $userOrToken->isAnonymous() ? 'for anonymous users' : 'for user "' . $userOrToken->getUsername() . '"';
                    throw $this->createAccessDeniedException('Access to ' . $this->getResourceName($resource) . ' denied ' . $forUser . '!', $permission, $userOrToken, $resource);
                } else {
                    throw $this->createAccessDeniedException('Unknown error while validating page access permissions!', $permission, $userOrToken, $resource);
                }
        }
        return $permission;
    }
    
    /**
     * Creates an access denied exception (AccessPermissionDeniedError by default).
     * 
     * Override this method to make the authorization point produce it's own exception type.
     * 
     * @param string $message
     * @param PermissionInterface $permission
     * @param UserImpersonationInterface $userOrToken
     * @param mixed $resource
     * @param string $alias
     * @param \Throwable $previous
     * 
     * @return AuthorizationExceptionInterface
     */
    protected function createAccessDeniedException(string $message, PermissionInterface $permission, UserImpersonationInterface $userOrToken, $resource = null, string $alias = null, \Throwable $previous = null) : AuthorizationExceptionInterface
    {
        return new AccessPermissionDeniedError($this, $permission, $userOrToken, $resource, $message, $alias, $previous);
    }
    
    protected abstract function getResourceName($resource) : string;
}