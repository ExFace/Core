<?php
namespace exface\Core\Interfaces\Security;

use Symfony\Component\Translation\Tests\StringClass;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
interface PermissionInterface
{    
    public function isPermitted() : bool;
    
    public function isDenied() : bool;
    
    public function isIndeterminate() : bool;
    
    public function isIndeterminatePermit() : bool;
    
    public function isIndeterminateDeny() : bool;
    
    public function isNotApplicable() : bool;
    
    public function getException() : ?\Throwable;
    
    public function getPolicy() : ?AuthorizationPolicyInterface;
    
    public function toXACMLDecision() : string;
}