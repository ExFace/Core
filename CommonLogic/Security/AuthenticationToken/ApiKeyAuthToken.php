<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Security\ApiKeyAuthenticationTokenInterface;

/**
 * Authentication token for API keys and access tokens with or without a username.
 * 
 * @author Andrej Kabachnik
 *
 */
class ApiKeyAuthToken implements ApiKeyAuthenticationTokenInterface
{
    private $username = null;
    
    private $apiKey = null;
    
    private $facade = null;
    
    /**
     * 
     * @param string $username
     * @param string $apiKey
     * @param FacadeInterface $facade
     */
    public function __construct(string $apiKey, string $username = null, FacadeInterface $facade = null)
    {
        $this->facade = $facade;
        $this->username = $username;
        $this->apiKey = $apiKey;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\ApiKeyAuthenticationTokenInterface::getApiKey()
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
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