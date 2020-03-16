<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\CommonLogic\Security\Authorization\Permission;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * Instantiates security policy permissions.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class PermissionFactory extends AbstractStaticFactory
{
    public static function createIndeterminate() : PermissionInterface
    {
        return new Permission(null, null, true);
    }
    
    public static function createNotApplicable() : PermissionInterface
    {
        return new Permission(null, null, null, true);
    }
    
    public static function createDenied() : PermissionInterface
    {
        return new Permission(true);
    }
    
    public static function createPermitted() : PermissionInterface
    {
        return new Permission(null, true);
    }
    
    /**
     * 
     * @param PolicyEffectDataType $effect
     * @throws InvalidArgumentException
     * @return PermissionInterface
     */
    public static function createFromPolicyEffect(PolicyEffectDataType $effect) : PermissionInterface
    {
        switch ($effect->__toString()) {
            case PolicyEffectDataType::PERMIT:
                return static::createPermitted();
            case PolicyEffectDataType::DENY:
                return static::createDenied();
            default:
                throw new InvalidArgumentException('Cannot create permission from policy effect "' . $effect->__toString() . '"!');
        }
    }
}