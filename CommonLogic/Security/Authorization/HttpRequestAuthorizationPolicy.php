<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\PolicyTargetDataType;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Factories\PermissionFactory;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Selectors\FacadeSelector;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Exceptions\Security\AuthorizationRuntimeError;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Exceptions\Security\AccessDeniedError;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\DataTypes\IPDataType;

/**
 * Policy for access to HTTP facades.
 * 
 * Allows to restrict access via regular expressions for `url_path_pattern`, `url_query_pattern` and `body_pattern`.
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpRequestAuthorizationPolicy implements AuthorizationPolicyInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $name = '';
    
    private $userRoleSelector = null;
    
    private $facadeSelector = null;
    
    private $conditionUxon = null;
    
    private $effect = null;
    
    private $urlPathRegex = null;
    
    private $urlQueryRegex = null;
    
    private $bodyRegex = null;
    
    private $clientIps = [];
    
    private $proxyIps = [];
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $name
     * @param PolicyEffectDataType $effect
     * @param array $targets
     * @param UxonObject $conditionUxon
     */
    public function __construct(WorkbenchInterface $workbench, string $name, PolicyEffectDataType $effect, array $targets, UxonObject $conditionUxon = null)
    {
        $this->workbench = $workbench;
        $this->name = $name;
        if ($str = $targets[PolicyTargetDataType::USER_ROLE]) {
            $this->userRoleSelector = new UserRoleSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::FACADE]) {
            $this->facadeSelector =  new FacadeSelector($this->workbench, $str);
        }
        
        $this->conditionUxon = $conditionUxon;
        $this->importUxonObject($conditionUxon);
        
        $this->effect = $effect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->conditionUxon ?? new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::authorize()
     */
    public function authorize(UserImpersonationInterface $userOrToken = null, FacadeInterface $facade = null, ServerRequestInterface $request = null): PermissionInterface
    {
        $applied = false;
        try {
            if ($facade === null) {
                throw new InvalidArgumentException('Cannot evalute facade access policy: no facade provided!');
            }
            
            // Make sure we have a user token
            if ($userOrToken instanceof AuthenticationTokenInterface) {
                $user = $this->workbench->getSecurity()->getUser($userOrToken);
            } else {
                $user = $userOrToken;
            }
            
            // Check if role matches
            if ($this->userRoleSelector !== null && $user->hasRole($this->userRoleSelector) === false) {
                return PermissionFactory::createNotApplicable($this, 'User role does not match');
            } else {
                $applied = true;
            }
            
            // Check if facade matches
            if ($this->facadeSelector !== null) {
                if ($facade->isExactly($this->facadeSelector) === true) {
                    $applied = true;
                } else {
                    return PermissionFactory::createNotApplicable($this, 'Facade does not match');
                }
            }
            
            // Check if URL matches
            if (null !== $pattern = $this->getUrlPathRegex()) {
                if (preg_match($pattern, $request->getUri()->getPath()) === 1) {
                    $applied = true;
                } else {
                    if (preg_last_error() !== PREG_NO_ERROR) {
                        return PermissionFactory::createIndeterminate(null, $this->getEffect(), $this, 'Cannot check `url_path_pattern` condition: failed mathing regular expression "' . str_replace("'", "\\'", $pattern) . '"');
                    }
                    return PermissionFactory::createNotApplicable($this, 'URL path does not match pattern `' . $pattern . '`');
                }
            }
            
            // Check if query (after `?`) matches
            if (null !== $pattern = $this->getUrlQueryRegex()) {
                if (preg_match($pattern, $request->getUri()->getQuery()) === 1) {
                    $applied = true;
                } else {
                    if (preg_last_error() !== PREG_NO_ERROR) {
                        return PermissionFactory::createIndeterminate(null, $this->getEffect(), $this, 'Cannot check `url_query_pattern`: failed mathing regular expression "' . str_replace("'", "\\'", $pattern) . '"');
                    }
                    return PermissionFactory::createNotApplicable($this, 'URL query (parameters) does not match pattern `' . $pattern . '`');
                }
            }
            
            // Check if body matches
            if (null !== $pattern = $this->getBodyRegex()) {
                if (preg_match($pattern, $request->getBody()->__toString()) === 1) {
                    $applied = true;
                } else {
                    if (preg_last_error() !== PREG_NO_ERROR) {
                        return PermissionFactory::createIndeterminate(null, $this->getEffect(), $this, 'Cannot check `body_pattern`: failed mathing regular expression "' . str_replace("'", "\\'", $pattern) . '"');
                    }
                    return PermissionFactory::createNotApplicable($this, 'Request body does not match pattern `' . $pattern . '`');
                }
            }
            
            // Check allowed IPs
            if (! empty($clientIps = $this->getClientIps())) {
                $ip = IPDataType::findIPAddress($request, $this->getProxyIps());
                if ($ip === null) {
                    return PermissionFactory::createNotApplicable($this, 'Cannot determin client IP in HTTP request');
                }
                $ipMatched = false;
                foreach ($clientIps as $mask) {
                    if (IPDataType::isIPInRange($ip, $mask)) {
                        $applied = true;
                        $ipMatched = true;
                        break;
                    }
                }
                if ($ipMatched === false) {
                    return PermissionFactory::createNotApplicable($this, 'Client IP "' . $ip . '" does not match policy IP range/mask');
                }
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this, 'No targets or conditions matched');
            }
        } catch (AuthorizationExceptionInterface | AccessDeniedError $e) {
            $facade->getWorkbench()->getLogger()->logException($e);
            return PermissionFactory::createDenied($this, $e->getMessage());
        } catch (\Throwable $e) {
            $facade->getWorkbench()->getLogger()->logException(new AuthorizationRuntimeError('Indeterminate permission due to error: ' . $e->getMessage(), null, $e));
            return PermissionFactory::createIndeterminate($e, $this->getEffect(), $this);
        }
        
        // If all targets are applicable, the permission is the effect of this condition.
        return PermissionFactory::createFromPolicyEffect($this->getEffect(), $this);
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getUrlPathRegex() : ?string
    {
        return $this->urlPathRegex;
    }
    
    /**
     * Apply the policy only to URL paths matching the provided regular expression
     * 
     * @uxon-property url_path_pattern
     * @uxon-type string
     * @uxon-template @/queue_topic_here/?$@i
     * 
     * @param string $value
     * @return HttpRequestAuthorizationPolicy
     */
    protected function setUrlPathPattern(string $value) : HttpRequestAuthorizationPolicy
    {
        $this->urlPathRegex = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getUrlQueryRegex() : ?string
    {
        return $this->urlQueryRegex;
    }
    
    /**
     * Apply the policy only to URLs with parameters matching the provided regular expression
     *
     * @uxon-property url_query_pattern
     * @uxon-type string
     * @uxon-template @&param=value(&|$)@i
     *
     * @param string $value
     * @return HttpRequestAuthorizationPolicy
     */
    protected function setUrlQueryPattern(string $value) : HttpRequestAuthorizationPolicy
    {
        $this->urlQueryRegex = $value;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getBodyRegex() : ?string
    {
        return $this->bodyRegex;
    }
    
    /**
     * Apply the policy only to requests with a body matching the provided regular expression
     *
     * @uxon-property body_pattern
     * @uxon-type string
     *
     * @param string $value
     * @return HttpRequestAuthorizationPolicy
     */
    protected function setBodyPattern(string $value) : HttpRequestAuthorizationPolicy
    {
        $this->bodyRegex = $value;
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getClientIps() : array
    {
        return $this->clientIps;
    }
    
    /**
     * Apply the policy only if the client IP matches one of the following ips/ranges
     * 
     * Each entry can be:
     * 
     * - Single IP address: e.g. `127.0.0.1` or `::1`
     * - IP v4 mask:
     *      - Wildcard-mask:  Class A (`10.*.*.*`), Class B (`180.16.*.*`) or Class C (`192.137.15.*`)
     *      - CIDR mask: e.g. `1.2.3/23` or `1.2.3.4/255.255.255.0`
     * - IP v4 range (start-end): `1.2.3.0-1.2.3.255`
     * - IP v6 mask:
     *      - CIDR mask: e.g. `2001:800::/21 OR 2001::/16`
     * 
     * @uxon-property client_ips
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $value
     * @return HttpRequestAuthorizationPolicy
     */
    protected function setClientIps(UxonObject $value) : HttpRequestAuthorizationPolicy
    {
        $this->clientIps = $value->toArray();
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getProxyIps() : array
    {
        return $this->proxyIps;
    }
    
    /**
     * Specifies valid proxy IPs if `client_ips` filtering is enabled.
     * 
     * Listing valid proxies is required if access through a proxy server should be possible!
     * If the client request goes through a proxy, the IP of the proxy will be treated as
     * client IP by default. However, if that proxy IP is known to be a proxy, we can try
     * to determine the original IP.
     * 
     * Long story short: if client may use proxies and the real client IP is of interest,
     * all valid proxy IPs MUST be listed here. Otherwise, you can also allow any request
     * coming from a proxy by adding the proxy IP to `client_ips`.
     * 
     * Each proxy entry must be a single IP address: e.g. `127.0.0.1` or `::1`
     * 
     * @uxon-property proxy_ips
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $value
     * @return HttpRequestAuthorizationPolicy
     */
    protected function setProxyIps(UxonObject $value) : HttpRequestAuthorizationPolicy
    {
        $this->proxyIps = $value->toArray();
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::getEffect()
     */
    public function getEffect() : PolicyEffectDataType
    {
        return $this->effect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::getName()
     */
    public function getName() : ?string
    {
        return $this->name;
    }
}