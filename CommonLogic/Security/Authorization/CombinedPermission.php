<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\Factories\PermissionFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;

/**
 * This permission is calculated by combining a given set of permissions unsing a specified algorithm.
 * 
 * @author Andrej Kabachnik
 *
 */
class CombinedPermission implements PermissionInterface
{
    private $algorithm = null;
    
    private $permissions = [];
    
    private $result = null;
    
    /**
     * 
     * @param PolicyCombiningAlgorithmDataType $algorithm
     * @param iterable $permissions
     */
    public function __construct(PolicyCombiningAlgorithmDataType $algorithm, iterable $permissions)
    {
        $this->algorithm = $algorithm;
        $this->result = $this->combinePermissions($permissions, $this->permissions);
    }
    
    public function isDenied(): bool
    {
        return $this->result->isDenied();
    }

    public function isPermitted(): bool
    {
        return $this->result->isPermitted();
    }

    public function isIndeterminate(): bool
    {
        return $this->result->isIndeterminate();
    }
    
    public function isIndeterminatePermit(): bool
    {
        return $this->result->isIndeterminatePermit();
    }
    
    public function isIndeterminateDeny(): bool
    {
        return $this->result->isIndeterminateDeny();
    }

    public function isNotApplicable(): bool
    {
        return $this->result->isNotApplicable();
    }
    
    public function getException(): ?\Throwable
    {
        return $this->result->getException();
    }

    public function getPolicy(): ?AuthorizationPolicyInterface
    {
        return null;
    }
    
    public function getPolicyCombiningAlgorithm() : PolicyCombiningAlgorithmDataType
    {
        return $this->algorithm;
    }
    
    /**
     * 
     * @return PermissionInterface[]
     */
    public function getCombinedPermissions() : array
    {
        return $this->permissions;
    }
    
    /**
     * Returns the resulting permission after applying the combining algorithm to the given permissions.
     * 
     * NOTE: since $permissions can be a generator and thus can only be iterated over once,
     * the second argument $resultArray can be used to store the actually evaluated permission.
     * 
     * @param iterable $permissions
     * @param array $resultArray
     * @return PermissionInterface
     */
    protected function combinePermissions(iterable $permissions, array &$resultArray = []) : PermissionInterface
    {
        try {
            return $this->combinePermissionsWithAlgorithm($permissions, $this->getPolicyCombiningAlgorithm(), $resultArray);
        } catch (\Throwable $e) {
            return PermissionFactory::createIndeterminate($e);
        }
    }
    
    /**
     * Applies the given combining algorithm to the provided $permissions.
     * 
     * NOTE: since $permissions can be a generator and thus can only be iterated over once,
     * the second argument $resultArray can be used to store the actually evaluated permission.
     * 
     * Keep in mind, that $resultArray will only include those permission really evaluated: e.g.
     * if the algorithm is `permit-unless-deny` and the first permission was a `deny`, the $resultArray
     * will only contain that first permission - no matter how many there were in total.
     * 
     * @param iterable $permissions
     * @param PolicyCombiningAlgorithmDataType $algorithm
     * @param array $resultArray
     * 
     * @return PermissionInterface
     */
    protected function combinePermissionsWithAlgorithm(iterable $permissions, PolicyCombiningAlgorithmDataType $algorithm, array &$resultArray) : PermissionInterface
    {
        $method = 'combineVia' . StringDataType::convertCaseUnderscoreToPascal(str_replace('-', '_', $algorithm->__toString()));
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], [$permissions, &$resultArray]);
        }
        throw new InvalidArgumentException('Unsupported policy combining algorithm "' . $algorithm->__toString() . '"!');
    }
    
    /**
     * The “Deny-unless-permit” combining algorithm is intended for those cases where a permit decision should have 
     * priority over a deny decision, and an “Indeterminate” or “NotApplicable” must never be the result. It is 
     * particularly useful at the top level in a policy structure to ensure that a PDP will always return a definite 
     * “Permit” or “Deny” result. This algorithm has the following behavior.
     * 
     * 1. If any decision is "Permit", the result is "Permit".
     * 2. Otherwise, the result is "Deny".
     * 
     * @param iterable $permissions
     * @param array $resultArray
     * @return PermissionInterface
     */
    protected function combineViaDenyUnlessPermit(iterable $permissions, array &$resultArray) : PermissionInterface
    {
        foreach ($permissions as $permission) {
            $resultArray[] = $permission;
            if ($permission->isPermitted()) {
                return new $permission;
            }
        }
        return PermissionFactory::createDenied();
    }
    
    /**
     * The “Permit-unless-deny” combining algorithm is intended for those cases where a deny decision should have priority 
     * over a permit decision, and an “Indeterminate” or “NotApplicable” must never be the result. It is particularly useful 
     * at the top level in a policy structure to ensure that a PDP will always return a definite “Permit” or “Deny” result. 
     * This algorithm has the following behavior.
     * 
     * 1. If any decision is "Deny", the result is "Deny".
     * 2. Otherwise, the result is "Permit".
     * 
     * @param iterable $permissions
     * @param array $resultArray
     * @return PermissionInterface
     */
    protected function combineViaPermitUnlessDeny(iterable $permissions, array &$resultArray) : PermissionInterface
    {
        foreach ($permissions as $permission) {
            $resultArray[] = $permission;
            if ($permission->isDenied()) {
                return $permission;
            }
        }
        return PermissionFactory::createPermitted();
    }
    
    public function toXACMLDecision() : string
    {
        return $this->result->toXACMLDecision();
    }
    
    public function __toString()
    {
        return $this->toXACMLDecision();
    }
}