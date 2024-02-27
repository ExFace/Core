<?php
namespace exface\Core\Interfaces\Security;

/**
 * Interface for key-based authentication tokens
 * 
 * @author Andrej Kabachnik
 *
 */
interface ApiKeyAuthenticationTokenInterface extends AuthenticationTokenInterface
{    
    /**
     * 
     * @return string
     */
    public function getApiKey() : string;
}