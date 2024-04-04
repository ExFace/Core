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
use exface\Core\Exceptions\Communication\CommunicationChannelConfigError;
use exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface;
use exface\Core\Events\Communication\OnMessageRoutedEvent;
use exface\Core\Events\Communication\OnMessageSentEvent;

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
        
        if ($this->connection === null) {
            throw new CommunicationChannelConfigError($this, 'No connection configured for communication channel ' . $this->__toString());
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
            $this->getWorkbench()->getLogger()->debug('Message `' . StringDataType::truncate($message->getText(), 50, false, true, true) . '` ignored as channel "' . $this->getName() . '" is muted!');
            return null;
        }
        try {
            if ($message instanceof Envelope) {
                $message = $this->createMessageFromEnvelope($message);
            }
            
            $this->getWorkbench()->eventManager()->dispatch(new OnMessageRoutedEvent($message, $this));
            
            $receipt = $this->getConnection()->communicate($message);
            
            $this->getWorkbench()->eventManager()->dispatch(new OnMessageSentEvent($receipt, $this));
        } catch (CommunicationExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CommunicationNotSentError($message, 'Failed to send message over channel "' . $this->getName() . '": ' . $e->getMessage(), null, $e, $this->getConnection());
        }
        
        return $receipt;
    }
    
    /**
     * 
     * @param Envelope $envelope
     * @return CommunicationMessageInterface
     */
    protected function createMessageFromEnvelope(Envelope $envelope) : CommunicationMessageInterface
    {
        return CommunicationFactory::createMessageFromPrototype($this->getWorkbench(), $this->getMessagePrototype(), $this->getMessageDefaults()->extend($envelope->exportUxonObject()));
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
    
    /**
     * 
     * @return string
     */
    public function __toString() : string
    {
        return '"' . $this->getName() . '" [' . $this->getAliasWithNamespace() . ']';
    }
}