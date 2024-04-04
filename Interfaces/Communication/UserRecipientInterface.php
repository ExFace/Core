<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\UserInterface;

interface UserRecipientInterface extends RecipientInterface
{
    /**
     * 
     * @return UserInterface
     */
    public function getUser() : UserInterface;
    
    /**
     * 
     * @return string
     */
    public function getUserUid() : string;
    
    /**
     * 
     * @return bool
     */
    public function isMuted() : bool;
}