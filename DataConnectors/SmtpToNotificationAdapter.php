<?php
namespace exface\Core\DataConnectors;

use Symfony\Component\Mime\Email;
use exface\Core\CommonLogic\UxonObject;
use Symfony\Component\Mailer\SentMessage;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Contexts\NotificationContext;
use exface\Core\Communication\Messages\NotificationMessage;
use Symfony\Component\Mailer\Envelope;

/**
 * Sends emails as in-app notifications instead of using a real SMTP server
 * 
 * Delivers email messages via in-app notifications to users, that have the original
 * recipient email address in their workbench user account.
 * 
 * This connector is especially useful for debugging emails as it allows to re-route
 * the entire email message right before it is passed to an SMTP server. For
 * best debugging experience set `send_message_dump` to `true` and enable
 * communication interception in the debug context menu.
 * 
 * @author Andrej Kabachnik
 *
 */
class SmtpToNotificationAdapter extends SmtpConnector
{
    private $sendMessageDump = false;
    
    /**
     * 
     * @param Email $email
     * @return SentMessage
     */
    protected function sendEmail(Email $email) : SentMessage
    {
        switch (true) {
            case $this->getSendMessageDump() === true:
                $notification = new NotificationMessage($this->getWorkbench(), new UxonObject([
                    'title' => 'Email dump: ' . $email->getSubject(),
                    'body_widget' => [
                        'widget_type' => 'Markdown',
                        'value' => $this->buildMarkdownDebug($email),
                        'hide_caption' => true,
                        'width' => 2
                    ]
                ]));
                break;
            case null !== $html = $email->getHtmlBody():
                $notification = new NotificationMessage($this->getWorkbench(), new UxonObject([
                    'title' => $email->getSubject(),
                    'text' => $html
                ]));
                break;
            default:
                $notification = new NotificationMessage($this->getWorkbench(), new UxonObject([
                    'title' => $email->getSubject(),
                    'text' => $email->getTextBody()
                ]));
        }
        
        $userIds = $this->getUserUids($email);
        if (! empty($userIds)) {
            NotificationContext::send($notification, $userIds);
        }
        
        // Return a Symfony-compliant SentMessage object
        // @see \Symfony\Component\Mailer\Transport\AbstractTransport::send()
        $sentMessage = new SentMessage($email, Envelope::create($email));
        return $sentMessage;
    }
    
    /**
     * 
     * @param Email $email
     * @return string[]
     */
    protected function getUserUids(Email $email) : array
    {
        $emailAddrs = array_merge($email->getTo(), $email->getCc(), $email->getBcc());
        $emailStrings = [];
        foreach ($emailAddrs as $addr) {
            $emailStrings[] = $addr->getAddress();
        }
        $emailStrings = array_unique($emailStrings);
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER');
        $ds->getFilters()->addConditionFromValueArray('EMAIL', $emailStrings);
        $uidCol = $ds->getColumns()->addFromUidAttribute();
        $ds->dataRead();
        return $uidCol->getValues();
    }
    
    /**
     * 
     * @return bool
     */
    protected function getSendMessageDump() : bool
    {
        return $this->sendMessageDump;
    }
    
    /**
     * Set to TRUE to include the complete email message including all headers instead of the body only
     * 
     * This is handy for debugging email messages without sending them to a real
     * SMTP server.
     * 
     * @uxon-property send_message_dump
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return SmtpToNotificationAdapter
     */
    protected function setSendMessageDump(bool $value) : SmtpToNotificationAdapter
    {
        $this->sendMessageDump = $value;
        return $this;
    }
}