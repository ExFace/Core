<?php

namespace exface\Core\Events;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\NameResolver;

/**
 * Action sheet event names consist of the qualified alias of the app followed by "Action" and the respective event type:
 * e.g.
 * exface.Core.ReadData.Action.Perform.Before, etc.
 * 
 * @author Andrej Kabachnik
 *        
 */
class ActionEvent extends ExfaceEvent
{

    private $action = null;

    public function getAction()
    {
        return $this->action;
    }

    public function setAction(ActionInterface $action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Events\ExfaceEvent::getNamespace()
     */
    public function getNamespace()
    {
        return $this->getAction()->getAliasWithNamespace() . NameResolver::NAMESPACE_SEPARATOR . 'Action';
    }
}