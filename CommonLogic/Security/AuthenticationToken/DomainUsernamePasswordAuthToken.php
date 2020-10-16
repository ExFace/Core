<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Authentication token for the typical username+password authentication.
 * 
 * @author Andrej Kabachnik
 *
 */
class DomainUsernamePasswordAuthToken extends UsernamePasswordAuthToken
{
    private $domain = null;
    
    /**
     * 
     * @param string $domain
     * @param string $username
     * @param string $password
     * @param FacadeInterface $facade
     */
    public function __construct(string $domain, string $username, string $password, FacadeInterface $facade = null)
    {
        parent::__construct($username, $password, $facade);
        $this->domain = $domain;
    }
    
    /**
     * 
     * @return string
     */
    public function getDomain() : string
    {
        return $this->domain;
    }
}