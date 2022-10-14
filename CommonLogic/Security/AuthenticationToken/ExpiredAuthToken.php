<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Special wrapper for expired tokens
 * 
 * @author Andrej Kabachnik
 *
 */
class ExpiredAuthToken implements AuthenticationTokenInterface
{
    private $expiredToken = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(AuthenticationTokenInterface $expiredToken)
    {
        $this->expiredToken = $expiredToken;
    }
    
    public function getExpiredToken() : AuthenticationTokenInterface
    {
        return $this->expiredToken;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::getFacade()
     */
    public function getFacade(): ?FacadeInterface
    {
        return $this->expiredToken->getFacade();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::getUsername()
     */
    public function getUsername() : ?string
    {
        return $this->expiredToken->getUsername();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticationTokenInterface::isAnonymous()
     */
    public function isAnonymous() : bool
    {
        return $this->expiredToken->isAnonymous();
    }
}