<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Security\ApiKeyAuthenticationTokenInterface;

/**
 * Authentication token for API keys and access tokens with or without a username.
 * 
 * @author Andrej Kabachnik
 * TODO add JWTTokenAuthTokenInterface with getJWTToken() method
 */
class JWTAuthToken
{
    private string $token;
    private ?string $username = null;
    private ?HttpFacadeInterface $facade = null;
    private ?array $decoded = null;

    /**
     * Create a new Azure Managed Identity token.
     *
     * @param int    $expirationTime
     * Timestamp in seconds, when this token expires.
     * @param string $accessToken
     * The base64 encoded token data.
     * @param string $subscriptionKey
     * The subscription key governing the resource you are trying to access.
     */
    public function __construct(string $token, string $username, ?HttpFacadeInterface $facade = null, ?array $decoded = null)
    {
        $this->token = $token;
        $this->facade = $facade;
        $this->username = $username;
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
    
    public function getJWTToken() : string
    {
        return $this->token;
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
        // TODO how and where to validate the token?
        // The JWT token will be handled by XXXAuthentictor. That authenticator will decode the token using public
        // keys from Azure tenant. If decoded, the authenticator must create a new instance of this class, which will
        // represent the authenticated token.
        return ! empty($this->decoded);
    }
}