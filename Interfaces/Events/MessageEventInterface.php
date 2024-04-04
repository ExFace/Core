<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Model\MessageInterface;

/**
 * Interface for events related to meta model messages
 *
 * @author Andrej Kabachnik
 *
 */
interface MessageEventInterface extends EventInterface
{
    /**
     * 
     * @return MessageInterface
     */
    public function getMessage() : MessageInterface;
}