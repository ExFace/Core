<?php
namespace exface\Core\Interfaces\Communication;

interface RecipientInterface
{
    /**
     * 
     * @return string
     */
    public function __toString() : string;
    
    /**
     * Returns TRUE if the other recipient is actually the same person - ideally even if the actual address is different
     * 
     * For example, if the recipients are users, but one is defined by the username
     * and the other by the UID, this should return TRUE if it is the same user
     * of the workbench.
     * 
     * @param RecipientInterface $otherRecipient
     * @return bool
     */
    public function is(RecipientInterface $otherRecipient) : bool;
}