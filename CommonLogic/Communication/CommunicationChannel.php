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
use exface\Core\Exceptions\Communication\CommunicationNotSentError;
use exface\Core\DataTypes\StringDataType;

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
    
    private $muted = false;
    
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::getName()
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::setName()
     */
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::setConnection()
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
     */
    public function getAlias()
    {
        return $this->getSelector()->toString();
    }
    
    /**
     * 
     * @return UxonObject
     */
    protected function getMessageDefaults() : UxonObject
    {
        return $this->defaultMessageUxon ?? new UxonObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::setMessageDefaults()
     */
    public function setMessageDefaults(UxonObject $value) : CommunicationChannel
    {
        $this->defaultMessageUxon = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function getMessagePrototype() : string
    {
        return $this->messagePrototype ?? TextMessage::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::setMessagePrototype()
     */
    public function setMessagePrototype(string $value) : CommunicationChannel
    {
        $this->messagePrototype = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::send()
     */
    public function send(CommunicationMessageInterface $message) : ?CommunicationReceiptInterface
    {
        if ($this->isMuted()) {
            $this->getWorkbench()->getLogger()->debug('Ignoring message "' . StringDataType::truncate($message->getText(), 20) . '" as channel "' . $this->getName() . '" is muted!');
            return null;
        }
        try {
            if ($message instanceof Envelope) {
                $message = $this->createMessageFromEnvelope($message);
            }
            return $this->getConnection()->communicate($message);
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException(new CommunicationNotSentError($message, 'Failed to message over channel "' . $this->getName() . '": ' . $e->getMessage()));
        }
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::isMuted()
     */
    public function isMuted() : bool
    {
        return $this->muted;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::setMuted()
     */
    public function setMuted(bool $value) : CommunicationChannelInterface
    {
        $this->muted = $value;
        return $this;
    }
}