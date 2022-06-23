<?php
namespace exface\Core\Communication\Messages;

use exface\Core\DataTypes\EmailPriorityDataType;
use exface\Core\DataTypes\HtmlDataType;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\EmailRecipientInterface;
use exface\Core\Communication\Recipients\EmailRecipient;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Communication\UserRecipientInterface;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\Communication\RecipientInterface;

/**
 * Email message to be sent to one or multiple email addresses, users or user groups
 * 
 * The to-addresses can be specified explicitly via `recipients` or by naming
 * `recipient_users` and `recipient_roles` to get the email addresses from.
 * 
 * The email consists of the body `text` (either plain text or HTML) and an optional `subject`. 
 * Additionally you can set a `priority`.
 * 
 * @author Andrej Kabachnik
 *
 */
class EmailMessage extends TextMessage
{    
    private $subject = null;
    
    private $priority = null;
    
    private $cc = [];
    
    private $bcc = [];
    
    private $replyTo = null;
    
    private $userEmailAttributeAlias = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if (null !== $this->userEmailAttributeAlias) {
            $uxon->setProperty('recipient_user_email_attribute', new UxonObject($this->userEmailAttributeAlias));
        }
        return $uxon;
    }
    
    
    /**
     * 
     * @return string|NULL
     */
    public function getSubject() : ?string
    {
        return $this->subject;
    }
    
    /**
     * Email subject
     * 
     * @uxon-property subject
     * @uxon-type string
     * 
     * @param string $value
     * @return EmailMessage
     */
    public function setSubject(string $value) : EmailMessage
    {
        $this->subject = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isHtml() : bool
    {
        return HtmlDataType::isValueHtml($this->getText());
    }
    
    /**
     * The body of the message - either plain text or HTML
     *
     * @uxon-property text
     * @uxon-type string
     *
     * {@inheritDoc}
     * @see \exface\Core\Communication\Messages\TextMessage::setText()
     */
    public function setText(string $value) : CommunicationMessageInterface
    {
        return parent::setText($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Communication\AbstractMessage::getRecipients()
     */
    public function getRecipients() : array
    {
        $recipients = parent::getRecipients();
        if ($emailAttrAlias = $this->getRecipientUserEmailAttribute()) {
            foreach ($recipients as $recipient) {
                $this->applyRecipientUserEmailAttribute($recipient, $emailAttrAlias);
            }
        }
        return $recipients;
    }
    
    /**
     * 
     * @param RecipientInterface $recipient
     * @param string $emailAttributeAlias
     * @return array
     */
    protected function applyRecipientUserEmailAttribute(RecipientInterface $recipient, string $emailAttributeAlias) : RecipientInterface
    {
        switch (true) {
            case $recipient instanceof UserRecipientInterface:
                $recipient->setEmailAttributeAlias($emailAttributeAlias);
                break;
            case $recipient instanceof RecipientGroupInterface:
                foreach ($recipient->getRecipients() as $r) {
                    $this->applyRecipientUserEmailAttribute($r, $emailAttributeAlias);
                }
                break;
        }
        return $recipient;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Communication\AbstractMessage::getRecipientAddresses()
     */
    public function getRecipientAddresses() : array
    {
        $emails = [];
        foreach (parent::getRecipientAddresses() as $addr) {
            if (is_string($addr)) {
                foreach (explode(';', $addr) as $email) {
                    if ($email !== '') {
                        $emails[] = $email;
                    }
                }
            }
        }
        return $emails;
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getPriority() : ?int
    {
        return $this->priority;
    }
    
    /**
     * Message priority: highest, high, normal, low, lowest.
     * 
     * @uxon-property priority
     * @uxon-type [highest,high,normal,low,lowest]
     * @uxon-default normal
     * 
     * @param string|int $value
     * @return EmailMessage
     */
    public function setPriority($value) : EmailMessage
    {
        $this->priority = EmailPriorityDataType::cast($value);
        return $this;
    }
    
    /**
     * One or more email addresses to send this message to. Each item of the list may also contain multiple emails separated by `;`.
     * 
     * This is the same as the `recipients` property. Use only one of them - otherwise the latter will override.
     * 
     * @uxon-property to
     * @uxon-type string
     * @uxon-template [""]
     * 
     * @param UxonObject $uxonArray
     * @return EmailMessage
     */
    public function setTo(UxonObject $uxonArray) : EmailMessage
    {
        return $this->setRecipients($uxonArray);
    }
    
    /**
     * 
     * @return EmailRecipientInterface[]
     */
    public function getRecipientsCC() : array
    {
        return $this->cc;
    }
    
    /**
     * One or more copy-to email addresses. Each item of the list may also contain multiple emails separated by `;`.
     * 
     * @uxon-property cc
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param string $uxonArray
     * @return EmailMessage
     */
    public function setCc(UxonObject $uxonArray) : EmailMessage
    {
        $this->cc = [];
        foreach ($uxonArray as $line) {
            foreach (explode(';', $line) as $addr) {
                $this->addCC($addr);
            }
        }
        return $this;
    }
    
    /**
     * 
     * @param EmailRecipientInterface|string $emailOrRecipient
     * @return EmailMessage
     */
    public function addCC($emailOrRecipient) : EmailMessage
    {
        $this->cc[] = $emailOrRecipient instanceof EmailRecipientInterface ? $emailOrRecipient : new EmailRecipient($emailOrRecipient);
        return $this;
    }
    
    /**
     *
     * @return EmailRecipientInterface[]
     */
    public function getRecipientsBCC() : array
    {
        return $this->bcc;
    }
    
    /**
     * One or more background-copy email addresses. Each item of the list may also contain multiple emails separated by `;`
     *
     * @uxon-property bcc
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param string $uxonArray
     * @return EmailMessage
     */
    public function setBcc(UxonObject $uxonArray) : EmailMessage
    {
        $this->cc = [];
        foreach ($uxonArray as $line) {
            foreach (explode(';', $line) as $addr) {
                $this->addBCC($addr);
            }
        }
        return $this;
    }
    
    /**
     *
     * @param EmailRecipientInterface|string $emailOrRecipient
     * @return EmailMessage
     */
    public function addBCC($emailOrRecipient) : EmailMessage
    {
        $this->bcc[] = $emailOrRecipient instanceof EmailRecipientInterface ? $emailOrRecipient : new EmailRecipient($emailOrRecipient);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getReplyTo() : ?EmailRecipientInterface
    {
        return $this->replyTo;
    }
    
    /**
     * The email address to reply to for this specific message
     * 
     * @uxon-property reply_to
     * @uxon-type string
     * 
     * @param EmailRecipientInterface|string $emailOrRecipient
     * @return EmailMessage
     */
    public function setReplyTo($emailOrRecipient) : EmailMessage
    {
        $this->replyTo = $emailOrRecipient instanceof EmailRecipientInterface ? $emailOrRecipient : new EmailRecipient($emailOrRecipient);
        return $this;
    }
    
    protected function getRecipientUserEmailAttribute() : ?string
    {
        return $this->userEmailAttributeAlias;
    }
    
    /**
     * Use a custom attribute alias to fetch the email address of a workbench user
     * 
     * @uxon-property recipient_user_email_attribute
     * @uxon-type email
     * 
     * @param string $attributeAliasWithRelationPath
     * @return EmailMessage
     */
    public function setRecipientUserEmailAttribute(string $attributeAliasWithRelationPath) : EmailMessage
    {
        $this->userEmailAttributeAlias = $attributeAliasWithRelationPath;
        return $this;
    }
}