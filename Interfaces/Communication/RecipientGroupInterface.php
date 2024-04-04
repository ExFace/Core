<?php
namespace exface\Core\Interfaces\Communication;

interface RecipientGroupInterface extends RecipientInterface
{
    /**
     * 
     * @return RecipientInterface[]
     */
    public function getRecipients() : array;
}