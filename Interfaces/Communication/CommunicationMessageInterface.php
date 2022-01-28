<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * Generic interface for communication messages: emails, sms, notifications, chat messages, etc.
 * 
 * @author andrej.kabachnik
 *
 */
interface CommunicationMessageInterface extends iCanBeConvertedToUxon
{
    /**
     * 
     * @return string
     */
    public function getText() : string;
    
    /**
     * 
     * @return string|NULL
     */
    public function getSubject() : ?string;
}