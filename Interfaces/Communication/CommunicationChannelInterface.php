<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\Selectors\CommunicationMessageSelectorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;

interface CommunicationChannelInterface extends WorkbenchDependantInterface, AliasInterface
{
    /**
     * 
     * @return string
     */
    public function getName(): string;
    
    /**
     * 
     * @param string $name
     * @return CommunicationChannelInterface
     */
    public function setName(string $name) : CommunicationChannelInterface;
    
    /**
     * 
     * @return CommunicationConnectionInterface
     */
    public function getConnection() : CommunicationConnectionInterface;
    
    /**
     * 
     * @param DataConnectionSelectorInterface|string $connectionOrSelectorOrString
     * @return CommunicationChannelInterface
     */
    public function setConnection($connectionOrSelectorOrString) : CommunicationChannelInterface;
    
    /**
     * 
     * @param UxonObject $value
     * @return CommunicationChannelInterface
     */
    public function setMessageDefaults(UxonObject $value) : CommunicationChannelInterface;
    
    /**
     * 
     * @param string $value
     * @return CommunicationChannelInterface
     */
    public function setMessagePrototype(string $value) : CommunicationChannelInterface;
    
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @return CommunicationReceiptInterface|NULL
     */
    public function send(CommunicationMessageInterface $message): ?CommunicationReceiptInterface;
    
    /**
     * 
     * @return CommunicationMessageSelectorInterface
     */
    public function getMessagePrototypeSelector() : CommunicationMessageSelectorInterface;
    
    /**
     * 
     * @return bool
     */
    public function isMuted() : bool;
    
    /**
     * 
     * @param bool $value
     * @return CommunicationChannelInterface
     */
    public function setMuted(bool $value) : CommunicationChannelInterface;
}