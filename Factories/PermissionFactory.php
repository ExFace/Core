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
    public static function createIndeterminate(\Throwable $error = null, $wouldBeEffect = null, AuthorizationPolicyInterface $policy = null, string $explanation = null) : PermissionInterface
    {
        if ($error !== null && $explanation === null) {
            $explanation = $error->getMessage();
        }
        if ($wouldBeEffect !== null) {
            $wouldBe = $wouldBeEffect instanceof PolicyEffectDataType ? $wouldBeEffect->__toString() : $wouldBeEffect;
            switch ($wouldBe) {
                case PolicyEffectDataType::PERMIT:
                    return new Permission(null, true, true, null, $policy, $error, $explanation);
                case PolicyEffectDataType::DENY:
                    return new Permission(true, null, true, null, $policy, $error, $explanation);
                default:
                    throw new InvalidArgumentException('Invalid would-be-effect "' . $wouldBeEffect . '" for indeterminate permission!');
            }
        }
        return new Permission(null, null, true, null, $policy, $error, $explanation);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createNotApplicable(AuthorizationPolicyInterface $policy = null, string $explanation = null) : PermissionInterface
    {
        return new Permission(null, null, null, true, $policy, null, $explanation);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createDenied(AuthorizationPolicyInterface $policy = null, string $explanation = null) : PermissionInterface
    {
        return new Permission(true, null, null, null, $policy, null, $explanation);
    }
    
    /**
     * 
     * @param AuthorizationPolicyInterface $policy
     * @return PermissionInterface
     */
    public static function createPermitted(AuthorizationPolicyInterface $policy = null, string $explanation = null) : PermissionInterface
    {
        return new Permission(null, true, null, null, $policy, null, $explanation);
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