<?php
namespace exface\Core\CommonLogic\Security\AuthenticationToken;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Security\JWTAuthenticationTokenInterface;
use JsonException;
use RuntimeException;

/**
 * Common authentication token implementation for JSON Web Tokens (JWT).
 * 
 * See https://www.jwt.io/introduction#what-is-json-web-token-structure
 *
 * @author Andrej Kabachnik
 */
class JWTAuthToken implements JWTAuthenticationTokenInterface
{
    private const JWT_ERROR_PREFIX = 'JWTAuthToken Error: ';
    private const ALLOWED_ALGORITHMS = ['RS256', 'RS384', 'RS512', 'ES384','ES256', 'HS256', 'HS384', 'HS512'];
    private string $token;
    private ?string $username;
    private ?HttpFacadeInterface $facade;
    private ?array $payload = null;
    
    private ?array $header = null;

    /**
     * @param string $token
     * @param string $username
     * @param HttpFacadeInterface|null $facade
     * @param array|null $decoded
     */
    public function __construct(
        string $token, 
        string $username, 
        ?HttpFacadeInterface $facade = null,
        ?array $payload = null)
    {
        $this->token = $token;
        $this->facade = $facade;
        $this->username = $username;
        $this->payload = $payload;
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
     * Gets the JWT header as an associative array. 
     * The header is the first part of the JWT token, which is a base64-encoded JSON string.
     * 
     * @return array
     */
    public function getHeader() : array
    {
        if ($this->header === null) {
            $parts = explode('.', $this->token);

            if (count($parts) !== 3) {
                throw new RuntimeException(self::JWT_ERROR_PREFIX . 'Invalid JWT format (header.payload.signature)');
            }
            
            try {
                $this->header = json_decode(base64_decode($parts[0]), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new RuntimeException(self::JWT_ERROR_PREFIX . 'Invalid JWT header: ' . $e->getMessage(), $e->getCode(), $e);
            }
        }
        return $this->header;
    }

    /**
     * Gets the encryption algorithm (alg) from the JWT header and checks if it is allowed. 
     * It is used for verifying the token signature.
     * 
     * You can not just trust the alg in the header, because the token is probably not verified at this point.
     * The public keys from the JWKS also contain the alg, but not in all cases (not in v1 tokens).
     * That is why we check the alg against a whitelist of allowed algorithms before using it.
     * 
     * @return string
     */
    public function getHeaderAlgorithm() : string
    {
        $headerAlg = $this->getHeader()['alg'] ?? null;
        
        if (!is_string($headerAlg) || !in_array($headerAlg, self::ALLOWED_ALGORITHMS, true)) {
            throw new RuntimeException(self::JWT_ERROR_PREFIX . "Unexpected encryption algorithm (alg): " . (string)$headerAlg);
        }
        
        return $headerAlg;
    }

    /**
     * @inheritDoc
     */
    public function isAnonymous(): bool
    {
        return $this->isValidated() === false;
    }

    /**
     * @return bool
     */
    protected function isValidated() : bool
    {
        // The token is considered validated if the payload has been successfully decoded in corresponding Authenticator and set here.
        return ! empty($this->payload);
    }
}