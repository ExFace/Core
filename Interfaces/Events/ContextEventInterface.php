<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Contexts\ContextInterface;

/**
 * Interface for events triggered in contexts.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ContextEventInterface extends EventInterface
{
    /**
     * Returns the context, that triggered the event.
     * 
     * @return ContextInterface
     */
    public function getContext() : ContextInterface;
}