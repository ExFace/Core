<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\CommonLogic\Security\Authorization\Permission;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;

/**
 * Instantiates security policy permissions.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class PermissionFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param \Throwable $error
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createIndeterminate(\Throwable $error = null, AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        return new Permission(null, null, true, null, $policy, $error);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createNotApplicable(AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        return new Permission(null, null, null, true);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createDenied(AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        return new Permission(true);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createPermitted(AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        return new Permission(null, true);
    }
    
    /**
     * 
     * @param PolicyEffectDataType $effect
     * @throws InvalidArgumentException
     * @return PermissionInterface
     */
    public static function createFromPolicyEffect(PolicyEffectDataType $effect, AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        switch ($effect->__toString()) {
            case PolicyEffectDataType::PERMIT:
                return static::createPermitted($policy);
            case PolicyEffectDataType::DENY:
                return static::createDenied($policy);
            default:
                throw new InvalidArgumentException('Cannot create permission from policy effect "' . $effect->__toString() . '"!');
        }
    }
}