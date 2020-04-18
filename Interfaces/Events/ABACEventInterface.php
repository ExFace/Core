<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;

/**
 * Interface for events being triggered along the ABAC authorization process.
 * 
 * @author Andrej Kabchnik
 *
 */
interface ABACEventInterface extends EventInterface
{
    /**
     * Returns the subject, who is being authorized: i.e. a user or the corresponding authentication token.
     * 
     * @return UserImpersonationInterface
     */
    public function getSubject() : UserImpersonationInterface;
    
    /**
     * Returns the resource, that is being accessed: e.g. a UI page, a meta object, etc.
     * 
     * @return object|NULL
     */
    public function getObject() : ?object;
    
    /**
     * Returns the action that is about to be performed.
     * 
     * @return ActionInterface|NULL
     */
    public function getAction() : ?ActionInterface;
    
    /**
     * Returns the current environment: i.e. the contexts.
     * 
     * @return ContextManagerInterface
     */
    public function getEnvironment() : ContextManagerInterface;
}