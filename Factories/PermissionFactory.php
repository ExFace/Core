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
    public static function createIndeterminate(\Throwable $error = null, PolicyEffectDataType $wouldBeEffect = null, AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        switch (true) {
            case ($wouldBeEffect && $wouldBeEffect->__toString() === PolicyEffectDataType::PERMIT):
                return new Permission(null, true, true, null, $policy, $error);
            case ($wouldBeEffect && $wouldBeEffect->__toString() === PolicyEffectDataType::DENY):
                return new Permission(true, null, true, null, $policy, $error);
        }
        return new Permission(null, null, true, null, $policy, $error);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createNotApplicable(AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        return new Permission(null, null, null, true, $policy);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createDenied(AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        return new Permission(true, null, null, null, $policy);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createPermitted(AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        return new Permission(null, true, null, null, $policy);
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
    
    /**
     *
     * @param PolicyEffectDataType $effect
     * @throws InvalidArgumentException
     * @return PermissionInterface
     */
    public static function createFromPolicyEffectInverted(PolicyEffectDataType $effect, AuthorizationPolicyInterface $policy = null) : PermissionInterface
    {
        switch ($effect->__toString()) {
            case PolicyEffectDataType::DENY:
                return static::createPermitted($policy);
            case PolicyEffectDataType::PERMIT:
                return static::createDenied($policy);
            default:
                throw new InvalidArgumentException('Cannot create permission from policy effect "' . $effect->__toString() . '"!');
        }
    }
}