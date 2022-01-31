<?php
namespace exface\Core\Interfaces\Communication;

interface CommunicationReceiptInterface
{
    public function getMessage() : CommunicationMessageInterface;
    
    public function getConnection() : CommunicationConnectionInterface;
    
    public function getSentTime() : \DateTimeInterface;
}