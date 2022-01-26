<?php
namespace exface\Core\Communication\Channels;

use exface\Core\CommonLogic\Communication\AbstractCommunicationChannel;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationAcknowledgementInterface;
use exface\Core\Communication\Messages\CommunicationAcknowledgement;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Contexts\NotificationContext;
use exface\Core\Interfaces\Communication\EnvelopeInterface;
use exface\Core\Communication\Messages\NotificationMessage;

class NotificationContextChannel extends AbstractCommunicationChannel
{
    private $messageOptionsClass = null;
    
    public function send(EnvelopeInterface $envelope) : CommunicationAcknowledgementInterface
    {
        $context = $this->getWorkbench()->getContext()->getScopeUser()->getContext(NotificationContext::class);
        $context->send($message);
        return new CommunicationAcknowledgement($message, $this);
    }
    
    protected function createMessage(UxonObject $payload) : NotificationMessage
    {
        
    }
    
    public function exportUxonObject()
    {
        return new UxonObject();
    }
}
