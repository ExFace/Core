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
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\CommonLogic\Selectors\UiPageGroupSelector;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\CommonLogic\Selectors\FacadeSelector;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
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
    
    private $conditionUxon = null;
    
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
        if ($str = $targets[PolicyTargetDataType::USER_ROLE]) {
            $this->userRoleSelector = new UserRoleSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::PAGE_GROUP]) {
            $this->pageGroupSelector = new UiPageGroupSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::META_OBJECT]) {
            $this->metaObjectSelector = new MetaObjectSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::ACTION]) {
            $this->actionSelector = new ActionSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::FACADE]) {
            $this->facadeSelector = new FacadeSelector($this->workbench, $str);
        }
        
        $this->conditionUxon = $conditionUxon;
        $this->importUxonObject($conditionUxon);
        
        $this->effect = $effect;
    }
    
    public function exportUxonObject()
    {
        return $this->conditionUxon ?? new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::authorize()
     */
    public function authorize(UserImpersonationInterface $userOrToken = null, UiPageInterface $page = null, MetaObjectInterface $object = null, ActionInterface $action = null, FacadeInterface $facade = null): PermissionInterface
    {
        $applied = false;
        try {
            if ($userOrToken instanceof AuthenticationTokenInterface) {
                $user = $this->workbench->getSecurity()->getUser($userOrToken);
            } else {
                $user = $userOrToken;
            }
            
            if ($this->userRoleSelector !== null && $user->hasRole($this->userRoleSelector) === false) {
                return PermissionFactory::createNotApplicable($this);
            } else {
                $applied = true;
            }    
            
            if ($page !== null) {
                if ($this->pageGroupSelector !== null && $page->isInGroup($this->pageGroupSelector) === false) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            }
            
            if ($object !== null) {
                if ($this->metaObjectSelector !== null && $object->is($this->metaObjectSelector) === false) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            }
            
            if ($action !== null) {
                if ($this->actionSelector !== null && $action->is($this->actionSelector) === false) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            }
            
            if ($facade !== null) {
                if ($this->facadeSelector !== null && $facade->is($this->facadeSelector) === false) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this);
            }
        } catch (\Throwable $e) {
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
}