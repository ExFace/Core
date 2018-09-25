<?php
namespace exface\Core\Events\Action;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\ActionEventInterface;

/**
 * 
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractActionEvent extends AbstractEvent implements ActionEventInterface
{
    private $action = null;
    
    /**
     * 
     * @param ActionInterface $action
     */
    public function __construct(ActionInterface $action)
    {
        $this->action = $action;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\ActionEventInterface::getAction()
     */
    public function getAction() : ActionInterface
    {
        return $this->action;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->action->getWorkbench();
    }
}