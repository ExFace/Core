<?php
namespace exface\Core\Events\Security;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Interfaces\Events\AuthorizationPointEventInterface;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Events\ABACEventInterface;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\CommonLogic\Security\Traits\AuthorizationDebugTrait;
use exface\Core\Widgets\DebugMessage;

/**
 * Event triggered when access to a resource or an action was authorized.
 * 
 * This event allows to perform additional authorization logic after the
 * core authorization system has given it's OK. This logic can modify
 * the subject or the action or even throw an AccessDeniedError to deny
 * the operation dispite the permission granted by the core's logic.
 * 
 * NOTE: it is not possible to change a deny-decision of the core
 * authorization system. For security reasons, all authorization extensions 
 * can only effect permit-decisions! 
 * 
 * @event exface.Core.Security.OnAuthorized
 * 
 * @author Andrej Kabachnik
 *
 */
class OnAuthorizedEvent extends AbstractEvent implements AuthorizationPointEventInterface, ABACEventInterface, iCanGenerateDebugWidgets
{
    use AuthorizationDebugTrait;
    
    private $ap = null;
    
    private $subject = null;
    
    private $object = null;
    
    private $action = null;
    
    private $permission = null;
    
    public function __construct(AuthorizationPointInterface $authPoint, PermissionInterface $permission, UserImpersonationInterface $userOrToken, $object = null, $action = null)
    {
        $this->ap = $authPoint;
        $this->subject = $userOrToken;
        $this->object = $object;
        $this->permission = $permission;
        $this->action = $action;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->ap->getWorkbench();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\AuthorizationPointEventInterface::getAuthorizationPoint()
     */
    public function getAuthorizationPoint(): AuthorizationPointInterface
    {
        return $this->ap;
    }
    
    public function getUser() : UserInterface
    {
        if ($this->subject instanceof AuthenticationTokenInterface) {
            return $this->getWorkbench()->getSecurity()->getUser($this->subject);
        }
        return $this->subject;
    }
    
    public function getAuthToken() : ?AuthenticationTokenInterface
    {
        if ($this->subject instanceof AuthenticationTokenInterface) {
            return $this->subject;
        }
        return null;
    }
    
    /**
     * 
     * @return UserImpersonationInterface
     */
    public function getSubject() : UserImpersonationInterface
    {
        return $this->subject;
    }

    /**
     * 
     * @return object|NULL
     */
    public function getObject()
    {
        return $this->object;
    }
    
    /**
     * 
     * @return ActionInterface|NULL
     */
    public function getAction() : ?ActionInterface
    {
        if ($this->action === null && $this->getObject() instanceof ActionInterface) {
            return $this->getObject();
        }
        return $this->action;
    }
    
    /**
     * 
     * @return ContextManagerInterface
     */
    public function getEnvironment(): ContextManagerInterface
    {
        return $this->getWorkbench()->getContext();
    }
    
    /**
     * 
     * @see AuthorizationDebugTrait::getPermission()
     */
    protected function getPermission(): PermissionInterface
    {
        return $this->permission;
    }
    
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message->addTab($this->createPoliciesTab($error_message));
        return $error_message;
    }
}