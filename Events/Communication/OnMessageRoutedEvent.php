<?php
namespace exface\Core\Events\Communication;

use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Events\CommunicationChannelEventInterface;
use exface\Core\Interfaces\Events\CommunicationMessageEventInterface;
use exface\Core\Events\AbstractEvent;

/**
 * Event fired once a communication message is routed to a channel - right before passing it to the connection.
 * 
 * @author Andrej Kabachnik
 *
 */
class OnMessageRoutedEvent extends AbstractEvent implements CommunicationChannelEventInterface, CommunicationMessageEventInterface
{
    private $message = null;
    
    private $channel = null;
    
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @param CommunicationChannelInterface $channel
     */
    public function __construct(CommunicationMessageInterface $message, CommunicationChannelInterface $channel)
    {
        $this->message = $message;
        $this->channel = $channel;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\CommunicationChannelEventInterface::getChannel()
     */
    public function getChannel(): CommunicationChannelInterface
    {
        return $this->channel;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->channel->getWorkbench();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\CommunicationMessageEventInterface::getMessage()
     */
    public function getMessage(): CommunicationMessageInterface
    {
        return $this->message;
    }
}