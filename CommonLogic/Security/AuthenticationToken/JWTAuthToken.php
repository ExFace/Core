<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Security\JWTAuthenticationTokenInterface;

/**
 * Authentication token for API keys and access tokens with or without a username.
 *
 * @author Andrej Kabachnik
 */
class JWTAuthToken implements JWTAuthenticationTokenInterface
{
    private string $token;
    private ?string $username = null;
    private ?HttpFacadeInterface $facade = null;
    private ?array $decoded = null;

    private string $expectedTenantId = '';
    private string $requiredRole = '';
    private string $expectedAudience = '';

    /**
     * @param string $token
     * @param string $username
     * @param HttpFacadeInterface|null $facade
     * @param string $expectedTenantId
     * @param string $expectedAudience
     * @param string $requiredRole
     * @param array|null $decoded
     */
    public function __construct(
        string $token, 
        string $username, 
        ?HttpFacadeInterface $facade = null,
        string $expectedTenantId,
        string $expectedAudience,
        string $requiredRole,
        ?array $decoded = null)
    {
        $this->token = $token;
        $this->facade = $facade;
        $this->username = $username;
        $this->expectedTenantId = $expectedTenantId;
        $this->expectedAudience = $expectedAudience;
        $this->requiredRole = $requiredRole;
        $this->decoded = $decoded;
    }

    /**
     * @inheritDoc
     */
    public function getFacade(): ?FacadeInterface
    {
        return $this->facade;
    }

    /**
     * @inheritDoc
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getJWTToken() : string
    {
        return $this->token;
    }

    /**
     * Gets expected tenant ID of the Azure tenant, which issued the token.
     * 
     * @return string
     */
    public function getExpectedTenantId() : string
    {
        return $this->expectedTenantId;
    }

    /**
     * Gets the required role for the token. 
     * The token must contain this role in order to be valid.
     * 
     * @return string
     */
    public function getRequiredRole() : string
    {
        return $this->requiredRole;
    }
    
    /**
     * Gets the API backend service identification that the token is permitted to call. 
     * Must match the audience configured during app registration in Entra ID (e.g. as ‘Application ID URI’).
     * 
     * @return string
     */
    public function getExpectedAudience() : string
    {
        return $this->expectedAudience;
    }

    /**
     * @inheritDoc
     */
    public function isAnonymous(): bool
    {
        return $this->isValidated() === false;
    }

    protected function isValidated() : bool
    {
        // The JWT token will be handled by AzureAppRegistrationAuthenticator. That authenticator will decode the token using public
        // keys from Azure tenant. If decoded, the authenticator must create a new instance of this class, which will
        // represent the authenticated token.
        return ! empty($this->decoded);
    }
}