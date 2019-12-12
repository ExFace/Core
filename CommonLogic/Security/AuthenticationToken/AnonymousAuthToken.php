<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\UserFactory;

/**
 * Authentication token for the anonymous user.
 * 
 * @author Andrej Kabachnik
 *
 */
class AnonymousAuthToken implements AuthenticationTokenInterface
{
    private $workbench = null;
    
    private $user = null;
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public function getPassword(): ?string
    {
        return null;
    }

    public function getUser(): UserInterface
    {
        if ($this->user === null) {
            $this->user = UserFactory::createAnonymous($this->workbench);
        }
        return $this->user;
    }

    public function getFacade(): FacadeInterface
    {
        return null;
    }

    public function getUsername() : ?string
    {
        return null;
    }
    
    public function isAnonymous() : bool
    {
        return true;
    }
}