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
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Exceptions\Security\AccessDeniedError;
use exface\Core\CommonLogic\Security\Authorization\Obligations\DataFilterObligation;

/**
 * Policy for access to data.
 * 
 * This type of policy can be used to restrict access to ceratin objects or subsets of data: e.g.
 * 
 * - Make a filter get automatically applied to data every time a ceratin role reads it
 * - Make users only see objects related to their own company/role or similar
 * - Forbid deleting an object for certain roles.
 * 
 * Possible targets:
 * 
 * - User role - policy applies to users with this role only
 * - Meta object - policy applies to data sheets with this meta object only
 * 
 * **NOTE:** by default, a policy applies to the target object and to any objects extending it!
 * You can change this by setting `apply_to_extending_objects` to `false`. 
 * 
 * Additional conditions:
 * 
 * - `operations` - restricts this policy to specific CRUD operations
 * - `add_filters` - a condition group to add to the filters of each data sheet this policy allows
 * (for the target object AND related objects if defined)
 * - `apply_to_related_objects` - allows to apply a single rule to an object and its relatives: e.g.
 * a rule defined for object `COMPANY` may be applied to `EMPLOYEE`, `ORDER` and any other object,
 * that has a relation to `COMPANY`.
 * - `apply_to_extending_objects` - controls, if the rule is applied to the specified objects only or
 * to all objects based on them.
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
    
    private $applyToExtendingObjects = true;
    
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
                $needExactMatch = $this->isApplicableToExtendingObjects() === false;
                switch (true) {
                    case $needExactMatch === false && $object->is($this->metaObjectSelector) === true:
                    case $needExactMatch === true && $object->isExactly($this->metaObjectSelector) === true:
                        $objectMatch = true;
                        break;
                    case $this->isApplicableToRelations():
                        foreach ($this->getApplyToRelations() as $relCfg) {
                            switch (true) {
                                case $needExactMatch === false && $object->is($relCfg->getRelatedObjectSelector()) === true:
                                case $needExactMatch === true && $object->isExactly($relCfg->getRelatedObjectSelector()) === true:
                                    $objectMatch = true;
                                    $relationPathToDataObj = $relCfg->getRelationPathFromPolicyObject();
                                    break 2;
                            }
                        }
                        break;
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
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this, 'No targets or conditions matched');
            } 
            
            $permission = PermissionFactory::createFromPolicyEffect($this->getEffect(), $this, $explanation);
            
            // Add filter obligations if required
            if ($applied === true && null !== $filtersUxon = $this->getFiltersUxon()) {
                if ($relationPathToDataObj !== null) {
                    $condGrp = ConditionGroupFactory::createFromUxon($dataSheet->getWorkbench(), $filtersUxon, MetaObjectFactory::create($this->metaObjectSelector));
                    $condGrp = $condGrp->rebase($relationPathToDataObj);
                } else {
                    $condGrp = ConditionGroupFactory::createFromUxon($dataSheet->getWorkbench(), $filtersUxon, $dataSheet->getMetaObject());
                }
                $permission->addObligation(new DataFilterObligation($condGrp));
            }
        } catch (AuthorizationExceptionInterface | AccessDeniedError $e) {
            $dataSheet->getWorkbench()->getLogger()->logException($e);
            return PermissionFactory::createDenied($this, $e->getMessage());
        } catch (\Throwable $e) {
            $dataSheet->getWorkbench()->getLogger()->logException(new AuthorizationRuntimeError('Indeterminate permission due to error: ' . $e->getMessage(), null, $e));
            return PermissionFactory::createIndeterminate($e, $this->getEffect(), $this);
        }
        
        // If all targets are applicable, the permission is the effect of this condition.
        return $permission;
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
     * For example, if you have an object called `COMPANY` and you need to make users of a certain
     * role only see `ORDER`s and `TASK`s of their own company, you can create the following policy:
     * 
     * ```
     *  {
     *      "add_filters":{
     *          "operator":"AND",
     *          "conditions":[
     *              {"expression": "EMPLOYEE__USER", "comparator": "==", "value": "=User()"}
     *          ]
     *      },
     *      "apply_to_related_objects":[
     *          {
     *              "related_object": "my.App.ORDER",
     *              "relation_path_from_policy_object": "CUSTOMER__ORDER"
     *          }, {
     *              "related_object": "my.App.TASK",
     *              "relation_path_from_policy_object": "TASK[OWNER_COMPANY]"}
     *          }
     *      ]
     *  }
     *  
     * ```
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
    
    /**
     * 
     * @return bool
     */
    protected function isApplicableToExtendingObjects() : bool
    {
        return $this->applyToExtendingObjects;
    }
    
    /**
     * Apply this policy to objects that extend from the object of this policy or any of the mentioned relations
     * 
     * For example, if you have a base object called `FILE` and another one called `IMPORT_FILE`, which
     * extends `FILE` and adds come special attributes or behaviors, you can control, if policies
     * for `FILE` will also be applied to `IMPORT_FILE` or not. By default, the will apply to any
     * extending objects too: including those, that extend `IMPORT_FILE` and any other decendant.
     * After all, `IMPORT_FILE` is still a file!
     * 
     * In particular, if there is a base object for many other objects - e.g. the base object for an
     * entire data source - this allows to define a single rule to control them all!
     * 
     * @uxon-property apply_to_extending_objects
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return DataAuthorizationPolicy
     */
    protected function setApplyToExtendingObjects(bool $value) : DataAuthorizationPolicy
    {
        $this->applyToExtendingObjects = $value;
        return $this;
    }
}