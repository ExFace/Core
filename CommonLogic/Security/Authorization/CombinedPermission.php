<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\Interfaces\Security\ObligationInterface;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\Factories\PermissionFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\DataTypes\PolicyEffectDataType;

/**
 * This permission is calculated by combining a given set of permissions unsing a specified algorithm.
 * 
 * A combined permission includes obligations from all of the contained permissions.
 * 
 * @author Andrej Kabachnik
 *
 */
class CombinedPermission implements PermissionInterface
{
    private $algorithm = null;
    
    /**
     * 
     * @var PermissionInterface[]
     */
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::isDenied()
     */
    public function isDenied(): bool
    {
        return $this->result->isDenied();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::isPermitted()
     */
    public function isPermitted(): bool
    {
        return $this->result->isPermitted();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::isIndeterminate()
     */
    public function isIndeterminate(): bool
    {
        return $this->result->isIndeterminate();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::isIndeterminatePermit()
     */
    public function isIndeterminatePermit(): bool
    {
        return $this->result->isIndeterminatePermit();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::isIndeterminateDeny()
     */
    public function isIndeterminateDeny(): bool
    {
        return $this->result->isIndeterminateDeny();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::isNotApplicable()
     */
    public function isNotApplicable(): bool
    {
        return $this->result->isNotApplicable();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::getException()
     */
    public function getException(): ?\Throwable
    {
        return $this->result->getException();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::getPolicy()
     */
    public function getPolicy(): ?AuthorizationPolicyInterface
    {
        return null;
    }
    
    /**
     * 
     * @return PolicyCombiningAlgorithmDataType
     */
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
     * @param PermissionInterface[] $permissions
     * @param array $resultArray
     * @return PermissionInterface
     */
    protected function combineViaDenyUnlessPermit(iterable $permissions, array &$resultArray) : PermissionInterface
    {
        $atLeastOnePermit = false;
        foreach ($permissions as $permission) {
            $resultArray[] = $permission;
            if ($permission->isPermitted()) {
                $atLeastOnePermit = true;
                // No obligations = unrestricted permission!
                if (! $permission->hasObligations()) {
                    break;
                }
                continue;
            }
        }
        if ($atLeastOnePermit === true) {
            return PermissionFactory::createPermitted(null, 'At least one `Permit`');
        }
        return PermissionFactory::createDenied(null, 'No `Permit`');
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
                return PermissionFactory::createDenied(null, 'At least one `Deny`');
            }
        }
        return PermissionFactory::createPermitted(null, 'No `Deny`');
    }
    
    /**
     * The permit overrides combining algorithm is intended for those cases where a permit decision should have priority over a deny decision.
     * This algorithm has the following behavior.
     * 
     * 1. If any decision is "Permit", the result is "Permit".
     * 2. Otherwise, if any decision is "Indeterminate{DP}", the result is "Indeterminate{DP}".
     * 3. Otherwise, if any decision is "Indeterminate{P}" and another decision is “Indeterminate{D} or Deny, the result is "Indeterminate{DP}".
     * 4. Otherwise, if any decision is "Indeterminate{P}", the result is "Indeterminate{P}".
     * 5. Otherwise, if any decision is "Deny", the result is "Deny".
     * 6. Otherwise, if any decision is "Indeterminate{D}", the result is "Indeterminate{D}".
     * 7. Otherwise, the result is "NotApplicable".
     * 
     * @param PermissionInterface[] $permissions
     * @param array $resultArray
     * @return PermissionInterface
     */
    protected function combineViaPermitOverrides(iterable $permissions, array &$resultArray) : PermissionInterface
    {
        $atLeastOneIndeterminateD = false;
        $atLeastOneIndeterminateP = false;
        $atLeastOneIndeterminate = false;
        $atLeastOneDeny = false;
        $atLeastOnePermit = false;
        foreach ($permissions as $permission) {
            $resultArray[] = $permission;
            if ($permission->isPermitted()) {
                $atLeastOnePermit = true;
                // No obligations = unrestricted permission!
                if (! $permission->hasObligations()) {
                    break;
                }
                continue;
            }
            if ($permission->isDenied()) {
                $atLeastOneDeny = true;
                continue;
            }
            if ($permission->isIndeterminateDeny()) {
                $atLeastOneIndeterminateD = true;
                continue;
            }
            if ($permission->isIndeterminatePermit()) {
                $atLeastOneIndeterminateP = true;
                continue;
            }
            if ($permission->isIndeterminate()) {
                $atLeastOneIndeterminate = true;
                continue;
            }
        }
        if ($atLeastOnePermit) {
            return PermissionFactory::createPermitted(null, 'At least one `Permit`');
        }
        if ($atLeastOneIndeterminate) {
            return PermissionFactory::createIndeterminate(null, null, null, 'At least one `Indeterminate`');
        }
        if ($atLeastOneIndeterminateP && ($atLeastOneIndeterminateD || $atLeastOneDeny)) {
            return PermissionFactory::createIndeterminate(null, null, null, 'At least one `Indeterminate{D}` or `Deny`');
        }
        if ($atLeastOneIndeterminateP === true) {
            return PermissionFactory::createIndeterminate(null, PolicyEffectDataType::PERMIT, null, 'At least one `Indeterminate{P}`');
        }
        if ($atLeastOneDeny) {
            return PermissionFactory::createDenied(null, 'At least one `Deny`');
        }
        if ($atLeastOneIndeterminateD === true) {
            return PermissionFactory::createIndeterminate(null, PolicyEffectDataType::DENY, null, 'At least one `Indeterminate{D}`');
        }
        return PermissionFactory::createNotApplicable(null, 'None applicable');
    }
    
    /**
     * The deny overrides combining algorithm is intended for those cases where a deny decision should have priority over a permit decision.
     * This algorithm has the following behavior.
     * 
     * 1. If any decision is "Deny", the result is "Deny".
     * 2. Otherwise, if any decision is "Indeterminate{DP}", the result is "Indeterminate{DP}".
     * 3. Otherwise, if any decision is "Indeterminate{D}" and another decision is “Indeterminate{P} or Permit, the result is "Indeterminate{DP}".
     * 4. Otherwise, if any decision is "Indeterminate{D}", the result is "Indeterminate{D}".
     * 5. Otherwise, if any decision is "Permit", the result is "Permit".
     * 6. Otherwise, if any decision is "Indeterminate{P}", the result is "Indeterminate{P}".
     * 7. Otherwise, the result is "NotApplicable".
     * 
     * @param PermissionInterface[] $permissions
     * @param array $resultArray
     * @return PermissionInterface
     */
    protected function combineViaDenyOverrides(iterable $permissions, array &$resultArray) : PermissionInterface
    {
        $atLeastOneIndeterminateD = false;
        $atLeastOneIndeterminateP = false;
        $atLeastOneIndeterminate = false;
        $atLeastOnePermit = false;
        foreach ($permissions as $permission) {
            $resultArray[] = $permission;
            if ($permission->isDenied()) {
                return PermissionFactory::createDenied(null, 'At least one `Deny`');
            }
            if ($permission->isPermitted()) {
                $atLeastOnePermit = true;
                continue;
            }
            if ($permission->isIndeterminateDeny()) {
                $atLeastOneIndeterminateD = true;
                continue;
            }
            if ($permission->isIndeterminatePermit()) {
                $atLeastOneIndeterminateP = true;
                continue;
            }
            if ($permission->isIndeterminate()) {
                $atLeastOneIndeterminate = true;
                continue;
            }
        }
        if ($atLeastOneIndeterminate) {
            return PermissionFactory::createIndeterminate(null, null, null, 'At least one `Indeterminate`');
        }
        if ($atLeastOneIndeterminateD && ($atLeastOneIndeterminateP || $atLeastOnePermit)) {
            return PermissionFactory::createIndeterminate(null, null, null, 'At least one `Indeterminate{D}` or `Deny`');
        }        
        if ($atLeastOneIndeterminateD === true) {
            return PermissionFactory::createIndeterminate(null, PolicyEffectDataType::DENY, null, 'At least one `Indeterminate{D}`');
        }
        if ($atLeastOnePermit) {
            return PermissionFactory::createPermitted(null, 'At least one `Permit`');
        }
        if ($atLeastOneIndeterminateP === true) {
            return PermissionFactory::createIndeterminate(null, PolicyEffectDataType::PERMIT, null, 'At least one `Indeterminate{P}`');
        }
        return PermissionFactory::createNotApplicable(null, 'None applicable');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::toXACMLDecision()
     */
    public function toXACMLDecision() : string
    {
        return $this->result->toXACMLDecision();
    }
    
    /**
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->toXACMLDecision();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::getExplanation()
     */
    public function getExplanation(): ?string
    {
        return $this->result->getExplanation();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::addObligation()
     */
    public function addObligation(ObligationInterface $obligation): PermissionInterface
    {
        $this->result->addObligation($obligation);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::hasObligations()
     */
    public function hasObligations(): bool
    {
        if ($this->result->hasObligations()) {
            return true;
        }
        foreach ($this->permissions as $permission) {
            if ($permission->hasObligations()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::getObligations()
     */
    public function getObligations(): array
    {
        $arr = $this->result->getObligations();
        foreach ($this->permissions as $permission) {
            $arr = array_merge($arr, $permission->getObligations());
        }
        return $arr;
    }
}