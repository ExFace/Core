<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\PolicyTargetDataType;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Factories\PermissionFactory;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Security\AuthorizationRuntimeError;
use exface\Core\Factories\MetaObjectFactory;

/**
 * Policy for access to data.
 * 
 * Possible targets:
 * 
 * - User role - policy applies to users with this role only
 * - Meta object - policy applies to data sheets with this meta object only
 * 
 * Additional conditions:
 * 
 * - `apply_to_related_objects` - defines rules to apply this policy to other objects too
 * - `operations` - restricts this policy to specific CRUD operations
 * - `add_filters` - a condition group to add to the filters of each data sheet this policy allows
 * (for the target object AND related objects if defined)
 * 
 * @author Andrej Kabachnik
 *
 */
class DataAuthorizationPolicy implements AuthorizationPolicyInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $name = '';
    
    private $userRoleSelector = null;
    
    private $metaObjectSelector = null;
    
    private $conditionUxon = null;
    
    private $effect = null;
    
    private $operations = null;
    
    private $filtersUxon = null;
    
    private $applyToRelations = [];
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $name
     * @param PolicyEffectDataType $effect
     * @param array $targets
     * @param UxonObject $conditionUxon
     */
    public function __construct(WorkbenchInterface $workbench, string $name, PolicyEffectDataType $effect, array $targets, UxonObject $conditionUxon = null)
    {
        $this->workbench = $workbench;
        $this->name = $name;
        if ($str = $targets[PolicyTargetDataType::USER_ROLE]) {
            $this->userRoleSelector = new UserRoleSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::META_OBJECT]) {
            $this->metaObjectSelector = new MetaObjectSelector($this->workbench, $str);
        } 
        
        $this->conditionUxon = $conditionUxon;
        $this->importUxonObject($conditionUxon);
        
        $this->effect = $effect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->conditionUxon ?? new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::authorize()
     */
    public function authorize(UserImpersonationInterface $userOrToken = null, DataSheetInterface $dataSheet = null, array $operations = []) : PermissionInterface
    {
        $applied = false;
        $explanation = '';
        try {
            if ($dataSheet === null) {
                throw new InvalidArgumentException('Cannot evalute data access policy: no data sheet provided!');
            }
            
            // Match meta object
            $relationPathToDataObj = null;
            if ($this->metaObjectSelector !== null) {
                $object = $dataSheet->getMetaObject();
                $objectMatch = false;
                if ($object->is($this->metaObjectSelector) === true) {
                    $objectMatch = true;
                } elseif ($this->isApplicableToRelations()) {
                    foreach ($this->getApplyToRelations() as $relCfg) {
                        if ($object->isExactly($relCfg->getRelatedObjectSelector()) === true) {
                            $objectMatch = true;
                            $relationPathToDataObj = $relCfg->getRelationPathFromPolicyObject();
                            break;
                        }
                    }
                }
                
                if ($objectMatch === false) {
                    return PermissionFactory::createNotApplicable($this, 'Meta object does not match');
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            // Match user
            if ($userOrToken instanceof AuthenticationTokenInterface) {
                $user = $this->workbench->getSecurity()->getUser($userOrToken);
            } else {
                $user = $userOrToken;
            }
            if ($this->userRoleSelector !== null && $user->hasRole($this->userRoleSelector) === false) {
                return PermissionFactory::createNotApplicable($this, 'User role does not match');
            } else {
                $applied = true;
            }
            
            // Match operation
            if ($applied === true && $this->hasOperationsRestrictions() === true) {
                if (empty(array_intersect($operations, $this->getOperations()))) {
                    return PermissionFactory::createNotApplicable($this, 'Operation does not match');
                } 
            } 
            
            // Add filters if required
            if ($applied === true && null !== $filtersUxon = $this->getFiltersUxon()) {
                if ($relationPathToDataObj !== null) {
                    $condGrp = ConditionGroupFactory::createFromUxon($dataSheet->getWorkbench(), $filtersUxon, MetaObjectFactory::create($this->metaObjectSelector));
                    $condGrp = $condGrp->rebase($relationPathToDataObj);
                } else {
                    $condGrp = ConditionGroupFactory::createFromUxon($dataSheet->getWorkbench(), $filtersUxon, $dataSheet->getMetaObject());
                }
                $dataFilters = $dataSheet->getFilters();
                $explanation .= ($explanation ? ' ' : '') . 'Added filter `' . $condGrp->toString() . '`.';
                switch (true) {
                    case $condGrp->getOperator() === $dataFilters->getOperator():
                        foreach ($condGrp->getConditions() as $cond) {
                            $dataFilters->addCondition($cond);
                        }
                        foreach ($condGrp->getNestedGroups() as $nestedGrp) {
                            $dataFilters->addNestedGroup($nestedGrp);   
                        }
                        break;
                    case $dataFilters->getOperator() === EXF_LOGICAL_AND:
                        $dataSheet->getFilters()->addNestedGroup($condGrp);
                        break;
                    default:
                        $newFilters = ConditionGroupFactory::createAND($dataSheet->getMetaObject());
                        $newFilters->addNestedGroup($condGrp);
                        if (! $dataFilters->isEmpty()) {
                            $newFilters->addNestedGroup($dataFilters);
                        }
                        break;
                }
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this, 'No targets or conditions matched');
            }
        } catch (\Throwable $e) {
            $dataSheet->getWorkbench()->getLogger()->logException(new AuthorizationRuntimeError('Indeterminate permission due to error: ' . $e->getMessage(), null, $e));
            return PermissionFactory::createIndeterminate($e, $this->getEffect(), $this);
        }
        
        // If all targets are applicable, the permission is the effect of this condition.
        return PermissionFactory::createFromPolicyEffect($this->getEffect(), $this, $explanation);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::getEffect()
     */
    public function getEffect() : PolicyEffectDataType
    {
        return $this->effect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::getName()
     */
    public function getName() : ?string
    {
        return $this->name;
    }
    
    /**
     * 
     * @return array|NULL
     */
    protected function getOperations() : ?array
    {
        return $this->operations;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasOperationsRestrictions() : bool
    {
        return $this->operations !== null;
    }
    
    /**
     * Apply policy to these operations only
     * 
     * @uxon-property operations
     * @uxon-type [read,create,update,delete][]
     * @uxon-template ["read","create","update","delete"]
     * 
     * @param UxonObject $arrayOfStrings
     * @return DataAuthorizationPolicy
     */
    protected function setOperations(UxonObject $arrayOfStrings) : DataAuthorizationPolicy
    {
        $this->operations = array_unique(array_map('strtolower', $arrayOfStrings->toArray()));
        return $this;
    }
    
    /**
     * 
     * @return UxonObject|NULL
     */
    protected function getFiltersUxon() : ?UxonObject
    {
        return $this->filtersUxon;
    }
    
    /**
     * Add this filter condition group to every data sheet applicable
     * 
     * @uxon-property add_filters
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "==","value": ""}]}
     * 
     * @param UxonObject $value
     * @return DataAuthorizationPolicy
     */
    protected function setAddFilters(UxonObject $value) : DataAuthorizationPolicy
    {
        $this->filtersUxon = $value;
        return $this;
    }
    
    /**
     * 
     * @return DataAuthorizationPolicyRelation[]
     */
    protected function getApplyToRelations() : array
    {
        return $this->applyToRelations;
    }
    
    /**
     * Apply this policy (including possible filters) to related objects too.
     * 
     * @uxon-property apply_to_related_objects
     * @uxon-type \exface\Core\CommonLogic\Security\Authorization\DataAuthorizationPolicyRelation[]
     * @uxon-template [{"related_object": "", "relation_path_from_policy_object": ""}]
     * 
     * @param UxonObject $arrayOfRelationPaths
     * @return DataAuthorizationPolicy
     */
    protected function setApplyToRelatedObjects(UxonObject $arrayOfRelationPaths) : DataAuthorizationPolicy
    {
        foreach ($arrayOfRelationPaths->getPropertiesAll() as $uxon) {
            $this->applyToRelations[] = new DataAuthorizationPolicyRelation($this, $uxon);
        }
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isApplicableToRelations() : bool
    {
        return ! empty($this->applyToRelations);
    }
    
    /**
     * 
     * @return WorkbenchInterface
     */
    public function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }
}