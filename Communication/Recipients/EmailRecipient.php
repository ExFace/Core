<?php
namespace exface\Core\Communication\Recipients;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Communication\EmailRecipientInterface;
use exface\Core\Interfaces\Communication\RecipientInterface;

class EmailRecipient implements EmailRecipientInterface
{
    private $email = null;
    
    /**
     * 
     * @param UserInterface $user
     */
    public function __construct(string $emailAddress)
    {
        $this->email = trim($emailAddress);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\EmailRecipientInterface::getEmail()
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientInterface::__toString()
     */
    public function __toString(): string
    {
        return 'mailto:' . $this->email;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\RecipientInterface::is()
     */
    public function is(RecipientInterface $otherRecipient): bool
    {
        if ($otherRecipient instanceof EmailRecipientInterface) {
            return strcasecmp($this->getEmail(), $otherRecipient->getEmail()) === 0;
        }
        return strcasecmp($this->__toString(), $otherRecipient->__toString()) === 0;
    }
}