<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Interface for events triggered within facades.
 * 
 * @author Andrej Kabachnik
 *
 */
interface FacadeEventInterface extends EventInterface
{
    /**
     * Returns the facade, that triggered the event.
     * 
     * @return FacadeInterface
     */
    public function getFacade() : FacadeInterface;
}