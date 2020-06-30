<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\PasswordAuthenticationTokenInterface;

/**
 * Authentication token for the typical username+password authentication.
 * 
 * @author Andrej Kabachnik
 *
 */
class UsernamePasswordAuthToken implements PasswordAuthenticationTokenInterface
{
    private $username = null;
    
    private $password = null;
    
    private $facade = null;
    
    /**
     * 
     * @param string $username
     * @param string $password
     * @param FacadeInterface $facade
     */
    public function __construct(string $username, string $password, FacadeInterface $facade = null)
    {
        $this->facade = $facade;
        $this->username = $username;
        $this->password = $password;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PasswordAuthenticationTokenInterface::getPassword()
     */
    public function getPassword(): string
    {
        return $this->password;
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
        return false;
    }
}