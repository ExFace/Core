<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

interface CommunicationReceiptInterface extends iCanGenerateDebugWidgets
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