<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\Security\PasswordAuthenticationTokenInterface;

class UsernamePasswordAuthToken implements PasswordAuthenticationTokenInterface
{
    private $username = null;
    
    private $password = null;
    
    private $facade = null;
    
    private $user = null;
    
    public function __construct(string $username, string $password, FacadeInterface $facade = null)
    {
        $this->facade = $facade;
        $this->username = $username;
        $this->password = $password;
    }
    
    public function getPassword(): string
    {
        return $this->password;
    }

    public function getUser(): UserInterface
    {
        if ($this->user === null) {
            $this->user = UserFactory::createFromModel($this->getWorkbench(), $this->getUsername());
        }
        return $this->user;
    }

    public function getFacade(): ?FacadeInterface
    {
        return $this->facade;
    }

    public function getUsername() : ?string
    {
        return $this->username;
    }

    protected function getWorkbench() : WorkbenchInterface
    {
        return $this->getFacade()->getWorkbench();
    }
    
    public function isAnonymous() : bool
    {
        return false;
    }
}