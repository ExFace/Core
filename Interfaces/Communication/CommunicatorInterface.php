<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\WorkbenchDependantInterface;

interface CommunicatorInterface extends WorkbenchDependantInterface
{
    /**
     * 
     * @param EnvelopeInterface $envelope
     * @return CommunicationAcknowledgementInterface[]
     */
    public function send(EnvelopeInterface $envelope) : array;
}