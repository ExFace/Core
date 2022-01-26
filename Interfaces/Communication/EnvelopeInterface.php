<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\UxonObject;

interface EnvelopeInterface
{
    /**
     * 
     * @return CommunicationChannelSelectorInterface|NULL
     */
    public function getChannelSelector() : ?CommunicationChannelSelectorInterface;
    
    /**
     * 
     * @return UxonObject
     */
    public function getPayloadUxon() : UxonObject;
    
    /**
     * 
     * @return RecipientInterface[]
     */
    public function getRecipients() : array;
}