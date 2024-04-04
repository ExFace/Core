<?php
namespace exface\Core\Interfaces\Security;

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
    
    public function getExplanation() : ?string;
    
    public function toXACMLDecision() : string;
    
    public function hasObligations() : bool;
    
    public function addObligation(ObligationInterface $obligation) : PermissionInterface;
    
    /**
     * 
     * @return ObligationInterface[]
     */
    public function getObligations() : array;
}