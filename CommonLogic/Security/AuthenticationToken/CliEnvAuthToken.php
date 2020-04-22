<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Security\PreAuthenticatedTokenInterface;

/**
 * Authentication token created from PHP's getenv('USER') or getenv('USERNAME').
 * 
 * @author Andrej Kabachnik
 *
 */
class CliEnvAuthToken implements PreAuthenticatedTokenInterface
{
    private $workbench = null;
    
    private $facade = null;
    
    private $username = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(FacadeInterface $facade = null)
    {
        $this->facade = $facade;
        $this->username = $this->getUsernameFromEnv();
    }
    
    /**
     * 
     * @return string|NULL
     */
    private function getUsernameFromEnv() : ?string
    {
        return getenv('USER') ? getenv('USER') : getenv('USERNAME');
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::isAnonymous()
     */
    public function isAnonymous() : bool
    {
        return $this->username === null;
    }
}