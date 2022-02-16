<?php
namespace exface\Core\Exceptions\Communication;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;

/**
 * Exception thrown if a communication channel is not configured properly.
 *
 * @author Andrej Kabachnik
 *        
 */
class CommunicationChannelConfigError extends RuntimeException
{
    private $channel = null;
    
    public function __construct(CommunicationChannelInterface $channel, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->channel = $channel;
    }
}
