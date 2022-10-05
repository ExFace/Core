<?php
namespace exface\Core\Events\Communication;

use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Events\CommunicationChannelEventInterface;
use exface\Core\Interfaces\Events\CommunicationMessageEventInterface;
use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;

/**
 * Event fired once a communication message is routed to a channel - right before passing it to the connection.
 * 
 * @author Andrej Kabachnik
 *
 */
class OnMessageSentEvent extends AbstractEvent implements CommunicationChannelEventInterface, CommunicationMessageEventInterface
{
    private $receipt = null;
    
    private $channel = null;
    
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @param CommunicationChannelInterface $channel
     */
    public function __construct(CommunicationReceiptInterface $message, CommunicationChannelInterface $channel)
    {
        $this->receipt = $message;
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
        return $this->receipt->getMessage();
    }
    
    /**
     * 
     * @return CommunicationReceiptInterface
     */
    public function getReceipt() : CommunicationReceiptInterface
    {
        return $this->receipt;
    }
}