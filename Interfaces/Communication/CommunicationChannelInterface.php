<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\Selectors\CommunicationMessageSelectorInterface;

interface CommunicationChannelInterface extends WorkbenchDependantInterface, AliasInterface
{
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @return CommunicationReceiptInterface
     */
    public function send(CommunicationMessageInterface $message) : CommunicationReceiptInterface;
    
    /**
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     * 
     * @return CommunicationConnectionInterface
     */
    public function getConnection() : CommunicationConnectionInterface;
    
    /**
     * 
     * @return CommunicationMessageSelectorInterface
     */
    public function getMessagePrototypeSelector() : CommunicationMessageSelectorInterface;
}