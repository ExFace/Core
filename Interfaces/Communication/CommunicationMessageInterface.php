<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * Generic interface for communication messages: emails, sms, notifications, chat messages, etc.
 * 
 * @author andrej.kabachnik
 *
 */
interface CommunicationMessageInterface extends iCanBeConvertedToUxon, WorkbenchDependantInterface
{
    
    /**
     * 
     * @return CommunicationChannelSelectorInterface|NULL
     */
    public function getChannelSelector() : ?CommunicationChannelSelectorInterface;
    
    /**
     * 
     * @return string
     */
    public function getText() : string;
    
    /**
     * 
     * @return CommunicationMessageInterface
     */
    public function setText(string $value) : CommunicationMessageInterface;
    
    /**
     * 
     * @return RecipientInterface[]
     */
    public function getRecipients() : array;
    
    /**
     * 
     * @return CommunicationMessageInterface
     */
    public function clearRecipients() : CommunicationMessageInterface;
    
    /**
     * 
     * @param RecipientInterface $recipient
     * @return CommunicationMessageInterface
     */
    public function addRecipient(RecipientInterface $recipient) : CommunicationMessageInterface;
    
    /**
     * 
     * @param CommunicationMessageInterface $anotherMsg
     * @return CommunicationMessageInterface
     */
    public static function fromOtherMessageType(CommunicationMessageInterface $anotherMsg) : CommunicationMessageInterface;
}