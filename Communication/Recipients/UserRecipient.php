<?php
namespace exface\Core\Communication\Recipients;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Communication\EmailRecipientInterface;
use exface\Core\Interfaces\Communication\UserRecipientInterface;
use exface\Core\Interfaces\Communication\RecipientInterface;

class UserRecipient implements UserRecipientInterface, EmailRecipientInterface
{
    private $user = null;
    
    private $emailAttributeAlias = null;
    
    /**
     * 
     * @param UserInterface $user
     */
    public function __construct(UserInterface $user)
    {
        $this->user = $user;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\UserRecipientInterface::getUser()
     */
    public function getUser(): UserInterface
    {
        return $this->user;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\UserRecipientInterface::getUserUid()
     */
    public function getUserUid(): string
    {
        return $this->getUser()->getUid();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\EmailRecipientInterface::getEmail()
     */
    public function getEmail(): ?string
    {
        if (null !== $emailAttr = $this->getEmailAttributeAlias()) {
            return $this->getUser()->getAttribute($emailAttr);
        }
        return $this->getUser()->getEmail();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientInterface::__toString()
     */
    public function __toString(): string
    {
        return 'user://' . $this->user->getUsername();
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getEmailAttributeAlias() : ?string
    {
        return $this->emailAttributeAlias;
    }

    /**
     * 
     * @param string $value
     * @return UserRecipient
     */
    public function setEmailAttributeAlias(string $value) : UserRecipient
    {
        $this->emailAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\UserRecipientInterface::isMuted()
     */
    public function isMuted() : bool
    {
        return $this->getUser()->isDisabledCommunication();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientInterface::is()
     */
    public function is(RecipientInterface $otherRecipient): bool
    {
        if ($otherRecipient instanceof UserRecipient) {
            return $this->getUser()->is($otherRecipient->getUser());
        }
        return strcasecmp($this->__toString(), $otherRecipient->__toString()) === 0;
    }
}