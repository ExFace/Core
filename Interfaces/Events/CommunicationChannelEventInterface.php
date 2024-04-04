<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Communication\CommunicationChannelInterface;

/**
 * Interface for events triggered in communication channels.
 * 
 * @author Andrej Kabachnik
 *
 */
interface CommunicationChannelEventInterface extends EventInterface
{
    /**
     * Returns the context, that triggered the event.
     * 
     * @return CommunicationChannelInterface
     */
    public function getChannel() : CommunicationChannelInterface;
}