<?php
namespace exface\Core\Interfaces\Communication;

interface PhoneRecipientInterface extends RecipientInterface
{
    public function getPhoneNumber() : ?string;
}