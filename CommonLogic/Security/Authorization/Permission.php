<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\Interfaces\Security\PermissionInterface;

class Permission implements PermissionInterface
{
    private $deny = null;
    
    private $permit = null;
    
    private $indeterminate = null;
    
    private $isNotApplicable = null;
    
    private $error = null;
    
    private $policy = null;
    
    /**
     * @deprecated use PermissionFactory instead!
     * 
     * @param bool $deny
     * @param bool $permit
     * @param bool $indeterminate
     * @param bool $isNotApplicable
     */
    public function __construct(
        bool $deny = null, 
        bool $permit = null, 
        bool $indeterminate = null, 
        bool $isNotApplicable = null,
        AuthorizationPolicyInterface $policy = null,
        \Throwable $error = null
    )
    {
        $this->deny = $deny;
        $this->permit = $permit;
        $this->indeterminate = $indeterminate;
        $this->isNotApplicable = $isNotApplicable;
        $this->error = $error;
        $this->policy = $policy;
    }
    
    public function isDenied(): bool
    {
        if ($this->isIndeterminate()) {
            return false;
        }
        return $this->deny ?? false;
    }

    public function isPermitted(): bool
    {
        if ($this->isIndeterminate()) {
            return false;
        }
        return $this->permit ?? false;
    }

    public function isIndeterminate(): bool
    {
        return $this->indeterminate ?? false;
    }
    
    public function isIndeterminatePermit(): bool
    {
        return $this->indeterminate && $this->permit;
    }
    
    public function isIndeterminateDeny(): bool
    {
        return $this->indeterminate && $this->deny;
    }

    public function isNotApplicable(): bool
    {
        return $this->isNotApplicable ?? false;
    }
    
    public function getException(): ?\Throwable
    {
        return $this->error;
    }

    public function getPolicy(): ?AuthorizationPolicyInterface
    {
        return $this->policy;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\PermissionInterface::toXACMLDecision()
     */
    public function toXACMLDecision() : string
    {
        switch (true) {
            case $this->indeterminate === true && $this->permit === true: return 'Indeterminate{P}';
            case $this->indeterminate === true && $this->deny === true: return 'Indeterminate{D}';
            case $this->indeterminate === true: return 'Indeterminate{DP}';
            case $this->isNotApplicable: return 'NotApplicable';
            case $this->permit === true: return 'Permit';
            case $this->deny === true: return 'Deny';
        }
    }
    
    public function __toString()
    {
        return $this->toXACMLDecision();
    }
}