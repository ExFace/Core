<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\Security\Traits\AuthorizationDebugTrait;
use exface\Core\Widgets\DebugMessage;

/**
 * Exception thrown if authorization fails on an authorization point.
 * 
 * This exception will generate a debug widget tab with a detailed overview 
 * of what happened in the authorization point including evaluated policies, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class AccessPermissionDeniedError extends AccessDeniedError implements AuthorizationExceptionInterface
{
    use AuthorizationDebugTrait;
    
    private $permission = null;
    
    private $authorizationPoint = null;
    
    private $subject = null;
    
    private $object = null;
    
    /**
     * 
     * @param AuthorizationPointInterface $authPoint
     * @param PermissionInterface $permission
     * @param UserImpersonationInterface $subject
     * @param mixed $object
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(AuthorizationPointInterface $authPoint, PermissionInterface $permission, UserImpersonationInterface $subject, $object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->permission = $permission;
        $this->authorizationPoint = $authPoint;
        $this->subject = $subject;
        $this->object = $object;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface::getPermission()
     */
    public function getPermission() : PermissionInterface
    {
        return $this->permission;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface::getAuthorizationPoint()
     */
    public function getAuthorizationPoint() : AuthorizationPointInterface
    {
        return $this->authorizationPoint;
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
     * @return mixed
     */
    public function getObject()
    {
        return $this->object;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message = parent::createDebugWidget($error_message);
        $error_message->addTab($this->createPoliciesTab($error_message));
        return $error_message;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\RuntimeException::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::ERROR;
    }
}