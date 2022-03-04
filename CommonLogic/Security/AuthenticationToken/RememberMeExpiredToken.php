<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;

/**
 * Authentication token generated if the lifetime of an otherwise valid RememberMeAuthToken ended.
 * 
 * @author Andrej Kabachnik
 *
 */
class RememberMeExpiredToken implements AuthenticationTokenInterface
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
        return true;
    }
}