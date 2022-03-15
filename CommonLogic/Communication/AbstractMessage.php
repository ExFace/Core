<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Communication\Recipients\UserRoleRecipient;
use exface\Core\Communication\Recipients\UserRecipient;
use exface\Core\Factories\UserFactory;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\Communication\Recipients\EmailRecipient;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Communication\UserRecipientInterface;
use exface\Core\Interfaces\Selectors\UserRoleSelectorInterface;
use exface\Core\Uxon\CommunicationMessageSchema;

/**
 * Base class for workbench-based messages providing common properties like `channel`, `recipient_users`, etc. 
 * 
 * @author andrej.kabachnik
 *
 */
abstract class AbstractMessage implements CommunicationMessageInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $channelSelector = null;
    
    private $recipientUserSelectors = [];
    
    private $recipientRoleSelectors = [];
    
    private $recipientAddresses = [];
    
    private $recipients = [];
    
    /**
     * 
     * @param UxonObject $payload
     * @param CommunicationChannelSelectorInterface $channelSelectorOrString
     * @param array $recipients
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon)
    {
        $this->workbench = $workbench;
        $this->importUxonObject($uxon);
    }
    
    /**
     * The channel to send the message through
     * 
     * @uxon-property channel
     * @uxon-type metamodel:exface.Core.COMMUNICATION_CHANNEL:ALIAS_WITH_NS
     * 
     * @param CommunicationChannelSelectorInterface|string $selectorOrString
     * @return CommunicationMessageInterface
     */
    public function setChannel($selectorOrString) : CommunicationMessageInterface
    {
        $this->channelSelector = $selectorOrString;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::getChannelSelector()
     */
    public function getChannelSelector(): ?CommunicationChannelSelectorInterface
    {
        if ($this->channelSelector !== null && ! $this->channelSelector instanceof CommunicationChannelSelectorInterface) {
            $this->channelSelector = new CommunicationChannelSelector($this->getWorkbench(), $this->channelSelector);
        }
        return $this->channelSelector;
    }

    /**
     * 
     * @return array
     */
    public function getRecipients() : array
    {
        if ($this->recipients === null) {
            $this->recipients = [];
            foreach ($this->getRecipientAddresses() as $addr) {
                // TODO move to factory
                if (false !== $filtered = filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $this->recipients[] = new EmailRecipient($filtered);
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
     * @uxon-type metamodel:exface.Core.USER:USERNAME[]
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfStrings
     * @return CommunicationMessageInterface
     */
    public function setRecipientUsers(UxonObject $arrayOfStrings) : CommunicationMessageInterface
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
     * @uxon-type metamodel:exface.Core.USER_ROLE:ALIAS_WITH_NS[]
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfStrings
     * @return CommunicationMessageInterface
     */
    public function setRecipientRoles(UxonObject $arrayOfStrings) : CommunicationMessageInterface
    {
        $this->recipients = null;
        $this->recipientRoleSelectors = $arrayOfStrings->toArray();
        return $this;
    }
    
    /**
     * List of explicit addresses (e.g. emails, phone numbers, etc.) to notify
     * 
     * @uxon-property recipients
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $arrayOfStrings
     * @return CommunicationMessageInterface
     */
    protected function setRecipients(UxonObject $arrayOfStrings) : CommunicationMessageInterface
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
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        if (null !== $val = $this->getChannelSelector()) {
            $uxon->setProperty('channel', $val->toString());
        }
        if (null !== $this->recipientAddresses) {
            $uxon->setProperty('recipients', new UxonObject($this->recipientAddresses));
        }
        if (null !== $this->recipientRoleSelectors) {
            $uxon->setProperty('recipient_roles', new UxonObject($this->recipientRoleSelectors));
        }
        if (null !== $this->recipientUserSelectors) {
            $uxon->setProperty('recipient_users', new UxonObject($this->recipientUserSelectors));
        }
        return $uxon;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::fromOtherMessageType()
     */
    public static function fromOtherMessageType(CommunicationMessageInterface $anotherMsg) : CommunicationMessageInterface
    {
        $uxon = new UxonObject();
        if (null !== $val = $anotherMsg->getChannelSelector()) {
            $uxon->setProperty('channel', $val->toString());
        }
        foreach ($anotherMsg->getRecipients() as $recipient) {
            switch (true) {
                case $recipient instanceof UserRoleSelectorInterface:
                    $uxon->appendToProperty('recipient_roles', $recipient->__toString());
                    break;
                case $recipient instanceof UserRecipientInterface:
                    $uxon->appendToProperty('recipient_users', $recipient->__toString());
                    break;
                default:
                    $uxon->appendToProperty('recipients', $recipient->__toString());
                    break;
            }
        }
        return new self($anotherMsg->getWorkbench(), $uxon);
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\WorkbenchInterface
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     *
     * @return string|NULL
     */
    public static function getUxonSchemaClass(): ?string
    {
        return CommunicationMessageSchema::class;
    }
}