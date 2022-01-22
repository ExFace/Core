<?php
namespace exface\Core\Interfaces\Communication;

interface CommunicationAcknowledgementInterface
{
    public function getMessage() : CommunicationMessageInterface;
    
    public function getChannel() : CommunicationChannelInterface;
    
    public function getSentTime() : \DateTimeInterface;
}