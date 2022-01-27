<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Communication\EnvelopeInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;
use exface\Core\Communication\Recipients\EmailRecipient;
use exface\Core\Communication\Recipients\UserRecipient;
use exface\Core\Factories\UserFactory;
use exface\Core\Communication\Recipients\UserRoleRecipient;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;

/**
 * A special envelope for notifications defined in UXON models.
 *
 * @author Andrej Kabachnik
 */
class NotificationEnvelope implements EnvelopeInterface, iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;
    
    private $workbench;
    
    private $recipientUserSelectors = [];
    
    private $recipientRoleSelectors = [];
    
    private $recipientAddresses = [];
    
    private $recipients = null;
    
    private $messageUxon = null;
    
    private $channelSelector = null;
    
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon)
    {
        $this->workbench = $workbench;
        $this->importUxonObject($uxon);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Communication\Envelope::getRecipients()
     */
    public function getRecipients() : array
    {
        if ($this->recipients === null) {
            $this->recipients = [];
            foreach ($this->getRecipientAddresses() as $addr) {
                // TODO move to factory
                if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $this->recipients[] = new EmailRecipient($addr);
                }
            }
            foreach ($this->getRecipientUsers() as $str) {
                $this->recipients[] = new UserRecipient(UserFactory::createFromUsernameOrUid($this->workbench, $str));
            }
            foreach ($this->getRecipientRoles() as $str) {
                $this->recipients[] = new UserRoleRecipient(new UserRoleSelector($this->workbench, $str));   
            }
        }
        return $this->recipients;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Communication\Envelope::getPayloadUxon()
     */
    public function getPayloadUxon() : UxonObject
    {
        return $this->messageUxon ?? new UxonObject();
    }
    
    /**
     * The model of the message to send
     * 
     * @uxon-property message
     * @uxon-type \exface\Core\Communication\Messages\GenericMessage
     * @uxon-template {"subject": "", "text": ""}
     * 
     * @param UxonObject $uxon
     * @return NotificationEnvelope
     */
    public function setMessage(UxonObject $uxon) : NotificationEnvelope
    {
        $this->messageUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Communication\Envelope::getChannelSelector()
     */
    public function getChannelSelector() : CommunicationChannelSelectorInterface
    {
        if (! ($this->channelSelector instanceof CommunicationChannelSelectorInterface)) {
            $this->channelSelector = new CommunicationChannelSelector($this->workbench, $this->channelSelector);
        }
        return $this->channelSelector;
    }
    
    /**
     * The channel to send the notification through
     * 
     * @uxon-property channel
     * @uxon-type metamodel:communication_channel
     * 
     * @param string $aliasOrClassOrPath
     * @return NotificationEnvelope
     */
    protected function setChannel(string $aliasOrClassOrPath) : NotificationEnvelope
    {
        $this->channelSelector = $aliasOrClassOrPath;
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getRecipientUsers() : array
    {
        return $this->recipientUserSelectors;
    }
    
    /**
     * List of user names aliases to notify
     *
     * @uxon-property recipient_users
     * @uxon-type metamodel:username
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfStrings
     * @return NotificationEnvelope
     */
    protected function setRecipientUsers(UxonObject $arrayOfStrings) : NotificationEnvelope
    {
        $this->recipients = null;
        $this->recipientUserSelectors = $arrayOfStrings->toArray();
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getRecipientRoles() : array
    {
        return $this->recipientRoleSelectors;
    }
    
    /**
     * List of user role aliases to notify
     * 
     * @uxon-property recipient_roles
     * @uxon-type metamodel:role
     * @uxon-template [""]
     * 
     * @param UxonObject $arrayOfStrings
     * @return NotificationEnvelope
     */
    protected function setRecipientRoles(UxonObject $arrayOfStrings) : NotificationEnvelope
    {
        $this->recipients = null;
        $this->recipientRoleSelectors = $arrayOfStrings->toArray();
        return $this;
    }
    
    /**
     * List of explicit addresses (e.g. emails, phone numbers, etc.) to notify
     * @param UxonObject $arrayOfStrings
     * @return NotificationEnvelope
     */
    protected function setRecipients(UxonObject $arrayOfStrings) : NotificationEnvelope
    {
        $this->recipients = null;
        $this->recipientAddresses = $arrayOfStrings->toArray();
        return $this;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getRecipientAddresses() : array
    {
        return $this->recipientAddresses;
    }
    
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }
}