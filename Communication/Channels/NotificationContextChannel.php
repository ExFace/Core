<?php
namespace exface\Core\Communication\Channels;

use exface\Core\CommonLogic\Communication\AbstractCommunicationChannel;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Contexts\NotificationContext;
use exface\Core\Interfaces\Communication\EnvelopeInterface;
use exface\Core\Communication\Messages\NotificationMessage;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\Communication\UserRecipientInterface;
use exface\Core\CommonLogic\Communication\CommunicationReceipt;

class NotificationContextChannel extends AbstractCommunicationChannel
{
    private $messageOptionsClass = null;
    
    public function send(EnvelopeInterface $envelope) : CommunicationReceiptInterface
    {
        $context = $this->getWorkbench()->getContext()->getScopeUser()->getContext(NotificationContext::class);
        $message = $this->createMessage($envelope->getPayloadUxon());
        
        $context->send($message, $this->getUserUids($envelope->getRecipients()));
        return new CommunicationReceipt($message, $this);
    }
    
    protected function createMessage(UxonObject $payload) : NotificationMessage
    {
        $msg = new NotificationMessage($payload->getProperty('text'), $payload->getProperty('subject'));
        return $msg;
    }
    
    protected function getUserUids(array $recipients) : array
    {
        $userUids = [];
        foreach ($recipients as $recipient) {
            switch (true) {
                case $recipient instanceof RecipientGroupInterface:
                    $userUids = array_merge($userUids, $this->getUserUids($recipient->getRecipients()));
                    break;
                case $recipient instanceof UserRecipientInterface:
                    $userUids[] = $recipient->getUserUid();
                    break;
                default:
                    // TODO
            }
        }
        return $userUids;
    }
    
    public function exportUxonObject()
    {
        return new UxonObject();
    }
}
