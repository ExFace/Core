<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\UserInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\Security\PasswordAuthenticationTokenInterface;

class UmsXmlAuthToken implements AuthenticationTokenInterface
{
    private $username = 'umsXmlRequests';
    
    private $facade = null;
    
    private $user = null;
    
    private $workbench = null;
    
    private $verifyCode = null;
    
    private $xml = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $verifyCode
     * @param string $xml
     * @param FacadeInterface $facade
     */
    public function __construct(WorkbenchInterface $workbench, string $verifyCode, string $xml, FacadeInterface $facade = null)
    {
        $this->facade = $facade;
        $this->workbench = $workbench;
        $this->verifyCode = $verifyCode;
        $this->xml = $xml;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::getUser()
     */
    public function getUser(): UserInterface
    {
        if ($this->user === null) {
            $this->user = UserFactory::createFromModel($this->getWorkbench(), $this->getUsername());
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
    
    public function getXml() : string
    {
        return $this->xml;
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
        return $this->username;
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
        return false;
    }
}