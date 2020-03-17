<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\Factories\PermissionFactory;

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
        switch ($algorithm->__toString()) {
            case PolicyCombiningAlgorithmDataType::DENY_UNLESS_PERMIT:
                foreach ($permissions as $permission) {
                    $resultArray[] = $permission;
                    if ($permission->isPermitted()) {
                        return new $permission;
                    }
                }
                return PermissionFactory::createDenied();
                break;
            case PolicyCombiningAlgorithmDataType::PERMIT_UNLESS_DENY:
                foreach ($permissions as $permission) {
                    $resultArray[] = $permission;
                    if ($permission->isDenied()) {
                        return $permission;
                    }
                }
                return PermissionFactory::createPermitted();
                break;
        }
    }
}