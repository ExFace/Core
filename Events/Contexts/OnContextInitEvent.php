<?php
namespace exface\Core\Events\Contexts;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\ContextEventInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;

/**
 * Event triggered when a context was initialized within a context scope.
 * 
 * @event exface.Core.Facades.OnContextInit
 * 
 * @author Andrej Kabachnik
 *
 */
class OnContextInitEvent extends AbstractEvent implements ContextEventInterface
{
    private $context = null;
    
    public function __construct(ContextInterface $context)
    {
        $this->context = $context;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->context->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\ContextEventInterface::getContext()
     */
    public function getContext(): ContextInterface
    {
        return $this->context;
    }
}