<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\AliasTrait;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;
use exface\Core\Communication\Messages\TextMessage;
use exface\Core\Communication\Messages\Envelope;
use exface\Core\Factories\CommunicationFactory;
use exface\Core\Interfaces\Selectors\CommunicationMessageSelectorInterface;
use exface\Core\CommonLogic\Selectors\CommunicationMessageSelector;

class CommunicationChannel implements CommunicationChannelInterface
{
    use AliasTrait {
        getAlias as getAliasFromSelector;
    }
    
    private $alias = null;
    
    private $name = '';
    
    private $workbench = null;
    
    private $connection = null;
    
    private $connectionSelector = null;
    
    private $appSelector = null;
    
    private $selector = null;
    
    private $defaultMessageUxon = null;
    
    private $messagePrototype = null;
    
    public function __construct(CommunicationChannelSelectorInterface $selector)
    {
        $this->selector = $selector;
        $this->workbench = $selector->getWorkbench();
    }
    
    /**
     * {@inheritdoc}
     * @see AliasSelectorTrait::getSelector()
     */
    public function getSelector() : AliasSelectorInterface
    {
        return $this->selector;
    }
    
    public function getName(): string
    {
        return $this->getName();
    }
    
    public function setName(string $name) : CommunicationChannelInterface
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::getConnection()
     */
    public function getConnection() : CommunicationConnectionInterface
    {
        if ($this->connection === null && $this->connectionSelector !== null) {
            $this->connection = DataConnectionFactory::createFromSelector($this->connectionSelector);
        }
        return $this->connection;
    }
    
    /**
     * 
     * @param CommunicationConnectionInterface|DataConnectionSelectorInterface|string $connectionOrSelectorOrString
     * @return CommunicationChannel
     */
    public function setConnection($connectionOrSelectorOrString) : CommunicationChannel
    {
        $this->connection = null;
        $this->connectionSelector = null;
        switch (true) {
            case $connectionOrSelectorOrString instanceof CommunicationConnectionInterface:
                $this->connection = $connectionOrSelectorOrString;
                break;
            case $connectionOrSelectorOrString instanceof DataConnectionSelectorInterface:
                $this->connectionSelector = $connectionOrSelectorOrString;
                break;
            default:
                $this->connectionSelector = new DataConnectionSelector($this->getWorkbench(), $connectionOrSelectorOrString);
        }
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     * @see AliasTrait::getAlias()
     */
    public function getAlias()
    {
        return $this->getSelector()->toString();
    }
    
    protected function getMessageDefaults() : UxonObject
    {
        return $this->defaultMessageUxon ?? new UxonObject();
    }
    
    /**
     * Default message model to use
     * 
     * @uxon-property message_defaults
     * @uxon-type \exface\Core\Communication\Messages\GenericMessage
     * @uxon-template {"":""}
     * 
     * @param UxonObject $value
     * @return CommunicationChannel
     */
    public function setMessageDefaults(UxonObject $value) : CommunicationChannel
    {
        $this->defaultMessageUxon = $value;
        return $this;
    }
    
    protected function getMessagePrototype() : string
    {
        return $this->messagePrototype ?? TextMessage::class;
    }
    
    public function setMessagePrototype(string $value) : CommunicationChannel
    {
        $this->messagePrototype = $value;
        return $this;
    }
    
    public function send(CommunicationMessageInterface $message): CommunicationReceiptInterface
    {
        if ($message instanceof Envelope) {
            $message = $this->createMessageFromEnvelope($message);
        }
        return $this->getConnection()->communicate($message);
    }
    
    /**
     * 
     * @param Envelope $envelope
     * @return CommunicationMessageInterface
     */
    protected function createMessageFromEnvelope(Envelope $envelope) : CommunicationMessageInterface
    {
        return CommunicationFactory::createMessageFromPrototype($this->getWorkbench(), $this->getMessagePrototype(), $envelope->exportUxonObject());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::getMessagePrototypeSelector()
     */
    public function getMessagePrototypeSelector() : CommunicationMessageSelectorInterface
    {
        return new CommunicationMessageSelector($this->getWorkbench(), $this->getMessagePrototype());
    }
}