<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Security\PermissionInterface;

class Permission implements PermissionInterface
{
    private $deny = null;
    
    private $permit = null;
    
    private $indeterminate = null;
    
    private $isNotApplicable = null;
    
    /**
     * @deprecated use PermissionFactory instead!
     * 
     * @param bool $deny
     * @param bool $permit
     * @param bool $indeterminate
     * @param bool $isNotApplicable
     */
    public function __construct(bool $deny = null, bool $permit = null, bool $indeterminate = null, bool $isNotApplicable = null) 
    {
        $this->deny = $deny;
        $this->permit = $permit;
        $this->indeterminate = $indeterminate;
        $this->isNotApplicable = $isNotApplicable;
    }
    
    public function isDenied(): bool
    {
        return $this->deny ?? false;
    }

    public function isPermitted(): bool
    {
        return $this->permit ?? false;
    }

    public function isIndeterminate(): bool
    {
        return $this->indeterminate ?? false;
    }

    public function isNotApplicable(): bool
    {
        return $this->isNotApplicable ?? false;
    }
}