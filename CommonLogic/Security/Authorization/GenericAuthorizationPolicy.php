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
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

class GenericAuthorizationPolicy implements AuthorizationPolicyInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $name = '';
    
    private $userRoleSelector = null;
    
    private $pageGroupSelector = null;
    
    private $metaObjectSelector = null;
    
    private $actionSelector = null;
    
    private $facadeSelector = null;
    
    private $configionUxon = null;
    
    private $effect = null;
    
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
        $this->userRoleSelector = $targets[PolicyTargetDataType::USER_ROLE];
        $this->pageGroupSelector = $targets[PolicyTargetDataType::PAGE_GROUP];
        $this->metaObjectSelector = $targets[PolicyTargetDataType::META_OBJECT];
        $this->actionSelector = $targets[PolicyTargetDataType::ACTION];
        $this->facadeSelector = $targets[PolicyTargetDataType::FACADE];
        $this->configionUxon = $conditionUxon;
        $this->effect = $effect;
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
    public function authorize(UserImpersonationInterface $userOrToken = null, UiPageInterface $page = null, MetaObjectInterface $object = null, ActionInterface $action = null, FacadeInterface $facade = null): PermissionInterface
    {
        $applied = false;
        if ($userOrToken instanceof AuthenticationTokenInterface) {
            $user = $this->workbench->getSecurity()->getUser($userOrToken);
        } else {
            $user = $userOrToken;
        }
        
        if ($this->userRoleSelector !== null) {
            if ($user->hasRole($this->userRoleSelector) === false) {
                return PermissionFactory::createNotApplicable();
            } else {
                $applied = true;
            }
        }        
        
        if ($this->pageGroupSelector) {
            if ($page === null || $page->isInGroup($this->pageGroupSelector) === false) {
                return PermissionFactory::createNotApplicable();
            } else {
                $applied = true;
            }
        }
        
        if ($this->metaObjectSelector) {
            if ($object === null || $object->is($this->metaObjectSelector) === false) {
                return PermissionFactory::createNotApplicable();
            } else {
                $applied = true;
            }
        }
        
        if ($this->actionSelector) {
            if ($action === null || $action->is($this->actionSelector) === false) {
                return PermissionFactory::createNotApplicable();
            } else {
                $applied = true;
            }
        }
        
        if ($this->facadeSelector) {
            if ($facade === null || $facade->is($this->facadeSelector) === false) {
                return PermissionFactory::createNotApplicable();
            } else {
                $applied = true;
            }
        }
        
        if ($applied === false) {
            return PermissionFactory::createNotApplicable();
        }
        
        // If all targets are applicable, the permission is the effect of this condition.
        return PermissionFactory::createFromPolicyEffect($this->getEffect());
    }
    
    protected function getEffect() : PolicyEffectDataType
    {
        return $this->effect;
    }
}