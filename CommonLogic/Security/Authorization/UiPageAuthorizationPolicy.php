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
use exface\Core\Exceptions\Security\AuthorizationRuntimeError;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Exceptions\Security\AccessDeniedError;

/**
 * Policy to restrict access to UI pages and navigation (menu) items.
 * 
 * By default, policies are only applied to published pages only. This results
 * in an `not applicable` authorization decision for unpublished pages, hiding
 * them by default. 
 * 
 * NOTE: the creator of a page can see it even if the policy evaluation
 * result in `not applicable` or `indeterminate`. This also means, that unpublished
 * pages created by the user will be visible to him or her unless other policies
 * with `apply_to_unpublished` = `true` exist for this user!
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
    
    private $applyToUnpublished = false;
    
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
                return PermissionFactory::createNotApplicable($this, 'User role does not match');
            } else {
                $applied = true;
            }
            
            if ($this->pageGroupSelector !== null && $menuItem->isInGroup($this->pageGroupSelector) === false) {
                return PermissionFactory::createNotApplicable($this, 'Page group does not match');
            } else {
                $applied = true;
            }
            
            // Return unapplicable if page is not published and the policy cannot be applied to unpublished items
            if ($menuItem->isPublished() === false && $this->isApplicableToUnpublished() === false) {
                return PermissionFactory::createNotApplicable($this, 'Page not published, but policy applies to published only');
            } else {
                $applied = true;
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this, 'No targets or conditions matched');
            }
        } catch (AuthorizationExceptionInterface | AccessDeniedError $e) {
            $menuItem->getWorkbench()->getLogger()->logException($e);
            return PermissionFactory::createDenied($this, $e->getMessage());
        } catch (\Throwable $e) {
            $menuItem->getWorkbench()->getLogger()->logException(new AuthorizationRuntimeError('Indeterminate permission for policy "' . $this->getName() . '" due to error: ' . $e->getMessage(), null, $e));
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
     * Set to `TRUE` to apply this policy to unpublished pages and menu items too.
     * 
     * By default, policies are only applied to published pages only. This results
     * in an `not applicable` authorization decision for unpublished pages, hiding
     * them by default. 
     * 
     * NOTE: the creator of a page can see it even if the policy evaluation
     * result in `not applicable` or `indeterminate`. This also means, that unpublished
     * pages created by the user will be visible to him or her unless other policies
     * with `apply_to_unpublished` = `true` exist for this user!
     * 
     * @uxon-property apply_to_unpublished
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return UiPageAuthorizationPolicy
     */
    protected function setApplyToUnpublished(bool $trueOrFalse) : UiPageAuthorizationPolicy
    {
        $this->applyToUnpublished = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isApplicableToUnpublished() : bool
    {
        return $this->applyToUnpublished;
    }
}