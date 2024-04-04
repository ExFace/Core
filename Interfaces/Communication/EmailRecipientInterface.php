<?php
namespace exface\Core\Interfaces\Communication;

interface EmailRecipientInterface extends RecipientInterface
{
    public function getEmail() : ?string;
}