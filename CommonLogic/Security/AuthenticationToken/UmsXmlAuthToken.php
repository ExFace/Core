<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\Security\PasswordAuthenticationTokenInterface;
use exface\Core\CommonLogic\Model\User;

class UmsXmlAuthToken implements AuthenticationTokenInterface
{
    
    private $facade = null;
    
    private $user = null;
    
    private $workbench = null;
    
    private $verifyCode = null;
    
    private $message = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $verifyCode
     * @param string $xml
     * @param FacadeInterface $facade
     * @param User $user
     */
    public function __construct(WorkbenchInterface $workbench, string $verifyCode, string $message, FacadeInterface $facade = null, User $user = null)
    {
        $this->facade = $facade;
        $this->workbench = $workbench;
        $this->verifyCode = $verifyCode;
        $this->message = $message;
        $this->user = $user;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::getUser()
     */
    public function getUser(): UserInterface
    {
        if ($this->user === null) {
            return UserFactory::createAnonymous($this->getWorkbench());
        }
        return $this->user;
    }
    
    /**
     * 
     * @return string
     */
    public function getVerifyCode() : string
    {
        return $this->verifyCode;
    }
    
    public function getMessage() : string
    {
        return $this->message;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::getFacade()
     */
    public function getFacade(): ?FacadeInterface
    {
        return $this->facade;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::getUsername()
     */
    public function getUsername() : ?string
    {
        return $this->getUser()->getUsername();
    }

    protected function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::isAnonymous()
     */
    public function isAnonymous() : bool
    {
        return $this->getUser()->isUserAnonymous();
    }
}