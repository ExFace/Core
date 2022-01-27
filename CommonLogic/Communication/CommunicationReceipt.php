<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\DateTimeInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;

class CommunicationReceipt implements CommunicationReceiptInterface
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
     * @see \exface\Core\Interfaces\Communication\CommunicationReceiptInterface::getMessage()
     */
    public function getMessage(): CommunicationMessageInterface
    {
        return $this->message;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationReceiptInterface::getSentTime()
     */
    public function getSentTime(): \DateTimeInterface
    {
        return $this->time;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationReceiptInterface::getChannel()
     */
    public function getChannel() : CommunicationChannelInterface
    {
        return $this->channel;
    }
}