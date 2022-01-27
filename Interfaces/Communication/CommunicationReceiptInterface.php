<?php
namespace exface\Core\Interfaces\Communication;

interface CommunicationReceiptInterface
{
    public function getMessage() : CommunicationMessageInterface;
    
    public function getChannel() : CommunicationChannelInterface;
    
    public function getSentTime() : \DateTimeInterface;
}