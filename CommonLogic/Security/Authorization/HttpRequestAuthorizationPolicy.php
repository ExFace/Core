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
            //SelectorFactory::createFacadeSelector($workbench, $selectorString)
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
            
            if ($userOrToken instanceof AuthenticationTokenInterface) {
                $user = $this->workbench->getSecurity()->getUser($userOrToken);
            } else {
                $user = $userOrToken;
            }
            
            if ($this->userRoleSelector !== null && $user->hasRole($this->userRoleSelector) === false) {
                return PermissionFactory::createNotApplicable($this, 'User role does not match');
            } else {
                $applied = true;
            }
            
            if ($this->facadeSelector !== null) {
                if ($facade->isExactly($this->facadeSelector) === true) {
                    $applied = true;
                } else {
                    return PermissionFactory::createNotApplicable($this, 'Facade does not match');
                }
            }
            
            if (null !== $pattern = $this->getUrlPathRegex()) {
                if (preg_match($pattern, $request->getUri()->getPath()) === 1) {
                    $applied = true;
                } else {
                    if (preg_last_error() !== PREG_NO_ERROR) {
                        return PermissionFactory::createIndeterminate(null, $this->getEffect(), $this, 'Cannot check `url_path_pattern` condition: failed mathing regular expression "' . str_replace("'", "\\'", $pattern) . '"');
                    }
                    return PermissionFactory::createNotApplicable($this, 'URL path does not match pattern');
                }
            }
            
            if (null !== $pattern = $this->getUrlPathRegex()) {
                if (preg_match($pattern, $request->getUri()->getQuery()) === 1) {
                    $applied = true;
                } else {
                    if (preg_last_error() !== PREG_NO_ERROR) {
                        return PermissionFactory::createIndeterminate(null, $this->getEffect(), $this, 'Cannot check `url_query_pattern`: failed mathing regular expression "' . str_replace("'", "\\'", $pattern) . '"');
                    }
                    return PermissionFactory::createNotApplicable($this, 'URL query (parameters) does not match pattern');
                }
            }
            
            if (null !== $pattern = $this->getBodyRegex()) {
                if (preg_match($pattern, $request->getBody()->__toString()) === 1) {
                    $applied = true;
                } else {
                    if (preg_last_error() !== PREG_NO_ERROR) {
                        return PermissionFactory::createIndeterminate(null, $this->getEffect(), $this, 'Cannot check `body_pattern`: failed mathing regular expression "' . str_replace("'", "\\'", $pattern) . '"');
                    }
                    return PermissionFactory::createNotApplicable($this, 'Request body does not match pattern');
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