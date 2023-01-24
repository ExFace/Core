<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\Selectors\CommunicationTemplateSelectorInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;

interface CommunicationTemplateInterface extends iCanBeConvertedToUxon, AliasInterface
{
    /**
     * 
     * @return UxonObject
     */
    public function getMessageUxon() : UxonObject;
    
    /**
     * 
     * @return string
     */
    public function getName() : string;
    
    /**
     * 
     * @return CommunicationTemplateSelectorInterface
     */
    public function getSelector() : CommunicationTemplateSelectorInterface;
    
    /**
     * 
     * @return string
     */
    public function getUid() : string;
    
    /**
     * 
     * @return CommunicationChannelSelectorInterface|NULL
     */
    public function getChannelSelector() : ?CommunicationChannelSelectorInterface;
}