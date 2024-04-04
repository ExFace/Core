<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Authentication token for a remembered user.
 * 
 * @author Andrej Kabachnik
 *
 */
class RememberMeAuthToken implements AuthenticationTokenInterface
{
    private $facade = null;
    
    private $username = null;
    
    private $expires = null;
    
    private $issued = null;
    
    private $refreshInterval = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(string $username = null, FacadeInterface $facade = null, int $issueTime = null, int $expirationTime = null, int $refreshInterval = null)
    {
        $this->facade = $facade;
        $this->username = $username;
        $this->expires = $expirationTime;
        $this->issued = $issueTime;
        $this->refreshInterval = $refreshInterval;
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
    
    /**
     * 
     * @return int
     */
    public function getExpirationTime() : int
    {
        return $this->expires;
    }
    
    /**
     * 
     * @return int
     */
    public function getLifetime() : int
    {
        if ($this->issued === null || $this->expires === null) {
            return 0;
        }
        return $this->expires - $this->issued;
    }
    
    /**
     * 
     * @return bool
     */
    public function isExpired() : bool
    {
        return $this->expires === null || $this->expires < time(); 
    }
    
    /**
     * 
     * @return int
     */
    public function getIssuedTime() : int
    {
        return $this->issued;
    }
    
    /**
     * 
     * @return int
     */
    public function getRefreshInterval() : int
    {
        return $this->refreshInterval;
    }
    
    /**
     * 
     * @return bool
     */
    public function isRefreshable() : bool
    {
        return $this->refreshInterval > 0 && $this->issued > 0;
    }
    
    /**
     * 
     * @return bool
     */
    public function isRefreshDue() : bool
    {
        return $this->isRefreshable() && time() > $this->issued + $this->refreshInterval;
    }
}