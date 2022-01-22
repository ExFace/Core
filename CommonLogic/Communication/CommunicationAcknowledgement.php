<?php
namespace exface\Core\Communication\Messages;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\DateTimeInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Communication\CommunicationAcknowledgementInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;

class CommunicationAcknowledgement implements CommunicationAcknowledgementInterface
{
    private $message = null;
    
    private $time = null;
    
    private $channel = null;
    
    public function __construct(CommunicationMessageInterface $message, CommunicationChannelInterface $channel, \DateTimeInterface $time = null)
    {
        $this->message = $message;
        $this->channel = $channel;
        $this->time = $time ?? new \DateTimeImmutable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationAcknowledgementInterface::getMessage()
     */
    public function getMessage(): CommunicationMessageInterface
    {
        return $this->message;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationAcknowledgementInterface::getSentTime()
     */
    public function getSentTime(): \DateTimeInterface
    {
        return $this->time;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationAcknowledgementInterface::getChannel()
     */
    public function getChannel() : CommunicationChannelInterface
    {
        return $this->channel;
    }
}