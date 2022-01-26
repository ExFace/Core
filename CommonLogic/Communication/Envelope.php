<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Communication\EnvelopeInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;

class Envelope implements EnvelopeInterface
{
    private $channelSelector = null;
    
    private $recipients = [];
    
    private $payloadUxon = null;
    
    /**
     * 
     * @param UxonObject $payload
     * @param CommunicationChannelSelectorInterface $channelSelectorOrString
     * @param array $recipients
     */
    public function __construct(UxonObject $payload, CommunicationChannelSelectorInterface $channelSelectorOrString = null, array $recipients = [])
    {
        $this->payloadUxon = $payload;
        $this->channelSelector = $channelSelectorOrString;
        $this->recipients = $recipients;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\EnvelopeInterface::getChannelSelector()
     */
    public function getChannelSelector(): ?CommunicationChannelSelectorInterface
    {
        return $this->channelSelector;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\EnvelopeInterface::getPayloadUxon()
     */
    public function getPayloadUxon(): UxonObject
    {
        return $this->payloadUxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\EnvelopeInterface::getRecipients()
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }
}