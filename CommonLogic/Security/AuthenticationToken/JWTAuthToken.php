<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Security\JWTAuthenticationTokenInterface;

/**
 * Common authentication token implementation for JSON Web Tokens (JWT).
 * 
 * See https://www.jwt.io/introduction#what-is-json-web-token-structure
 *
 * @author Andrej Kabachnik
 */
class JWTAuthToken implements JWTAuthenticationTokenInterface
{
    private const JWT_ERROR_PREFIX = 'Azure App Registration Authenticator Error: ';
    
    private string $token;
    private ?string $username = null;
    private ?HttpFacadeInterface $facade = null;
    private ?array $payload = null;
    private ?array $header = null;

    private string $expectedAudience = '';

    /**
     * @param string $token
     * @param string $username
     * @param HttpFacadeInterface|null $facade
     * @param array|null $payload
     */
    public function __construct(
        string $token, 
        string $username, 
        ?HttpFacadeInterface $facade = null,
        ?array $header = null,
        ?array $payload = null
    )
    {
        $this->token = $token;
        $this->facade = $facade;
        $this->username = $username;
        $this->payload = $payload;
        $this->header = $header;
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
     * Returns the `aud` claim
     * 
     * The "aud" (audience) claim identifies the recipients that the JWT is
     * intended for.  Each principal intended to process the JWT MUST
     * identify itself with a value in the audience claim.  If the principal
     * processing the claim does not identify itself with a value in the
     * "aud" claim when this claim is present, then the JWT MUST be
     * rejected.  In the general case, the "aud" value is an array of case-
     * sensitive strings, each containing a StringOrURI value.  In the
     * special case when the JWT has one audience, the "aud" value MAY be a
     * single case-sensitive string containing a StringOrURI value.  The
     * interpretation of audience values is generally application specific.
     * Use of this claim is OPTIONAL.
     * 
     * @return string
     */
    public function getClaimAudience() : string
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
        return ! empty($this->payload);
    }
    
    public function getPayload() : ?array
    {
        return $this->payload;
    }
    
    public function getHeader() : ?array
    {
        // Reading the kid from the header.
        $parts = explode('.', $this->token);
        if (count($parts) !== 3) {
            throw new RuntimeException(self::JWT_ERROR_PREFIX . 'Invalid JWT format (header.payload.signature)');
        }
        [$headerB64] = $parts;

        try {
            $header = json_decode($this->base64UrlDecode($headerB64), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(self::JWT_ERROR_PREFIX . 'Invalid JWT header: ' . $e->getMessage(), $e->getCode(), $e);
        }
        // TODO cache header in private var
        return $header;
    }
    
    public function getAlgorithm() : ?string
    {
        $header = $this->getHeader();
        return $header['alg'] ?? null;
    }
}