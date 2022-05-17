<?php
namespace exface\Core\Communication\Recipients;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Communication\EmailRecipientInterface;
use exface\Core\Interfaces\Communication\UserRecipientInterface;

class UserRecipient implements UserRecipientInterface, EmailRecipientInterface
{
    private $user = null;
    
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
        return $this->getUser()->getEmail();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientInterface::__toString()
     */
    public function __toString(): string
    {
        return $this->user->getUsername();
    }
}