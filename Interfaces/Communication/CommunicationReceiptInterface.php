<?php
namespace exface\Core\Interfaces\Communication;

interface CommunicationReceiptInterface
{
    /**
     * 
     * @return CommunicationMessageInterface
     */
    public function getMessage() : CommunicationMessageInterface;
    
    /**
     * 
     * @return CommunicationConnectionInterface
     */
    public function getConnection() : CommunicationConnectionInterface;
    
    /**
     * 
     * @return \DateTimeInterface
     */
    public function getSentTime() : \DateTimeInterface;
    
    /**
     * 
     * @return bool
     */
    public function isSent() : bool;
}