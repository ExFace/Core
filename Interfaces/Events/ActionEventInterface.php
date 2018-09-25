<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Actions\ActionInterface;

interface ActionEventInterface extends EventInterface
{
    /**
     * Returns the action, for which the event was triggered.
     * 
     * @return ActionInterface
     */
    public function getAction() : ActionInterface;
}