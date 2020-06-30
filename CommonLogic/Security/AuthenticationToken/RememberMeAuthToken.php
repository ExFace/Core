<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Authentication token for the anonymous user.
 * 
 * @author Andrej Kabachnik
 *
 */
class RememberMeAuthToken implements AuthenticationTokenInterface
{
    private $workbench = null;
    
    private $facade = null;
    
    private $username = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(string $username = null, FacadeInterface $facade = null)
    {
        $this->facade = $facade;
        $this->username = $username;
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