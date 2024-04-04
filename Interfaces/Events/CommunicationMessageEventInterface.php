<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;

/**
 * Interface for events triggered for communication messages.
 * 
 * @author Andrej Kabachnik
 *
 */
interface CommunicationMessageEventInterface extends EventInterface
{
    /**
     * Returns the context, that triggered the event.
     * 
     * @return CommunicationMessageInterface
     */
    public function getMessage() : CommunicationMessageInterface;
}