<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\Interfaces\Selectors\MetaObjectSelectorInterface;

/**
 * Defines rules for a data authorization policy to be applied no only to its target object, but also to a related objects
 * 
 * @author Andrej Kabachnik
 *
 */
class DataAuthorizationPolicyRelation
{
    use ImportUxonObjectTrait;
    
    private $policy = null;
    
    private $metaObjectSelector = null;
    
    private $relationPathString = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     */
    public function __construct(DataAuthorizationPolicy $policy, UxonObject $uxon)
    {
        $this->policy = $policy;
        $this->importUxonObject($uxon);
    }
    
    /**
     * Alias of UID of the related object to be applied to
     * 
     * @uxon-property related_object
     * @uxon-type metamodel:object
     * 
     * @param string $selector
     * @return DataAuthorizationPolicyRelation
     */
    protected function setRelatedObject(string $selector) : DataAuthorizationPolicyRelation
    {
        $this->metaObjectSelector = new MetaObjectSelector($this->policy->getWorkbench(), $selector);
        return $this;
    }
    
    public function getRelatedObjectSelector() : MetaObjectSelectorInterface
    {
        return $this->metaObjectSelector;
    }
    
    /**
     * Relation from the meta object of the policy to the object to be applied to
     * 
     * @uxon-property relation_path_from_policy_object
     * @uxon-type metamodel:relation
     * 
     * @param string $path
     * @return DataAuthorizationPolicyRelation
     */
    protected function setRelationPathFromPolicyObject(string $path) : DataAuthorizationPolicyRelation
    {
        $this->relationPathString = $path;
        return $this;
    }
    
    public function getRelationPathFromPolicyObject() : string
    {
        return $this->relationPathString;
    }
}