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
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\CommonLogic\Selectors\UiPageGroupSelector;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * Policy to restrict access to UI pages and navigation (menu) items.
 * 
 * @author Andrej Kabachnik
 *
 */
class UiPageAuthorizationPolicy implements AuthorizationPolicyInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $name = '';
    
    private $userRoleSelector = null;
    
    private $pageGroupSelector = null;
    
    private $conditionUxon = null;
    
    private $effect = null;
    
    private $hideUnpublished = true;
    
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
        
        $this->conditionUxon = $conditionUxon;
        $this->importUxonObject($conditionUxon);
        
        $this->effect = $effect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->conditionUxon ?? new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::authorize()
     */
    public function authorize(UserImpersonationInterface $userOrToken = null, UiMenuItemInterface $menuItem = null): PermissionInterface
    {
        $applied = false;
        try {
            if ($menuItem === null) {
                throw new InvalidArgumentException('Cannot evalute page access policy: no page or menu item provided!');
            }
            
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
            
            if ($this->pageGroupSelector !== null && $menuItem->isInGroup($this->pageGroupSelector) === false) {
                return PermissionFactory::createNotApplicable($this);
            } else {
                $applied = true;
            }
            
            // Deny access to unpublished menu items unless their author is the current user
            if ($menuItem->isPublished() === false && $this->getHideUnpublished() === true) {
                $creatorSelector = $menuItem->getCreatedByUserSelector();
                switch (true) {
                    case $creatorSelector->isUid() && $creatorSelector->toString() === $user->getUid():
                        break;
                    case $creatorSelector->isUsername() && $creatorSelector->toString() === $user->getUsername():
                        break;
                    default:
                        return PermissionFactory::createFromPolicyEffectInverted($this->getEffect(), $this);
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
    
    /**
     * Set to FALSE to show give access to unpublished pages and menu items.
     * 
     * By default, only the creator of an unpublished page can see it. All
     * other users require a special policy matching that page and having
     * `hide_unpublished` set to `true`;
     * 
     * @uxon-property hide_unpublished
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return UiPageAuthorizationPolicy
     */
    protected function setHideUnpublished(bool $trueOrFalse) : UiPageAuthorizationPolicy
    {
        $this->hideUnpublished = $trueOrFalse;
        return $this;
    }
    
    protected function getHideUnpublished() : bool
    {
        return $this->hideUnpublished;
    }
}