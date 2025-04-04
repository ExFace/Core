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
 * ## Possible targets:
 * 
 * - User role - policy applies to users with this role only
 * - Meta object - policy applies to data sheets with this meta object only
 * - App - policy applies to all objects of this app
 * 
 * **NOTE:** by default, a policy applies to the target object and to any objects extending it!
 * You can change this by setting `apply_to_extending_objects` to `false`. 
 * 
 * ## Additional conditions:
 * 
 * - `operations` - restricts this policy to specific CRUD operations
 * - `add_filters` - a condition group to add to the filters of each data sheet this policy allows
 * - `add_filters_in_scope` - similar to `add_filters`, but defining a filtering scope explicitly
 * - `apply_to_related_objects` - allows to apply a single rule to an object and its relatives: e.g.
 * a rule defined for object `COMPANY` may be applied to `EMPLOYEE`, `ORDER` and any other object,
 * that has a relation to `COMPANY`.
 * - `apply_to_extending_objects` - controls, if the rule is applied to the specified objects only or
 * to all objects based on them.
 * 
 * ## Permissions for subsets of data via filters
 * 
 * A common use case of data policies is to limit the visibility of data: e.g. a user should only
 * see the orders of his or her company. This can be achieved using permitting permissions with 
 * `add_filters` or `add_filters_in_scope`. These filters will be automatically applied to read/write
 * operations on the object of the policy.
 * 
 * Filters can also be applied to related objects via `apply_to_extending_objects`: e.g. allowing 
 * a user to see only a certain company and
 * - only order placed by that company
 * - only order positions of orders placed by that company
 * 
 * Each of these addtional restrictions needs to be an item in `apply_to_extending_objects`.
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
    
    private $appUid = null;
    
    private $conditionUxon = null;
    
    private $effect = null;
    
    private $operations = null;
    
    private $filtersUxon = null;
    
    private $filterScope = null;
    
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
        if (null !== $str = $targets[PolicyTargetDataType::USER_ROLE]) {
            $this->userRoleSelector = new UserRoleSelector($this->workbench, $str);
        }
        if (null !== $str = $targets[PolicyTargetDataType::META_OBJECT]) {
            $this->metaObjectSelector = new MetaObjectSelector($this->workbench, $str);
        } 
        if (null !== $str = $targets[PolicyTargetDataType::APP]) {
            $this->appUid = $str;
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
            
            // Match app
            if ($this->appUid !== null) {
                $object = $dataSheet->getMetaObject();
                // Not applicable if app match required, but object belongs to another app
                // Otherwise applied because apps match
                if (strcasecmp($object->getApp()->getUid(), $this->appUid) !== 0) {
                    return PermissionFactory::createNotApplicable($this, 'App does not match app of object');
                } else {
                    $applied = true;
                }
            } else {
                // Applied if app target not set
                $applied = true;
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this, 'No targets or conditions matched');
            } 
            
            $permission = PermissionFactory::createFromPolicyEffect($this->getEffect(), $this, $explanation);
            
            // Add filter obligations if required
            if ($applied === true && null !== $filtersUxon = $this->getFiltersUxon()) {
                if ($relationPathToDataObj !== null) {
                    $condGrp = ConditionGroupFactory::createFromUxon($dataSheet->getWorkbench(), $filtersUxon, MetaObjectFactory::createFromSelector($this->metaObjectSelector));
                    $condGrp = $condGrp->rebase($relationPathToDataObj);
                } else {
                    $condGrp = ConditionGroupFactory::createFromUxon($dataSheet->getWorkbench(), $filtersUxon, $dataSheet->getMetaObject());
                }
                
                $permission->addObligation(new DataFilterObligation($condGrp, $this->getFilterScope()));
            }
        } catch (AuthorizationExceptionInterface | AccessDeniedError $e) {
            $dataSheet->getWorkbench()->getLogger()->logException($e);
            return PermissionFactory::createDenied($this, $e->getMessage());
        } catch (\Throwable $e) {
            $dataSheet->getWorkbench()->getLogger()->logException(new AuthorizationRuntimeError('Indeterminate permission for policy "' . $this->getName() . '" due to error: ' . $e->getMessage(), null, $e));
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
     * If multiple policies with filters are applied, the filters will be combined
     * via OR among policies with the same filtering scope and via AND if the scopes
     * are different. 
     * 
     * If not set explicitly, the scope is the target meta object of the policy. 
     *  
     * For example, if there are policies, that allow a user to only see a certain
     * company, and policies, limiting the view to a country, a user that has
     * roles for Company1 and Company2 in Germany will receive the following filter:
     * `(Company = "Company1" OR Company = "Company2") AND Country = "Germany"`.
     * 
     * If you need the filters from multiple policies with different target objects 
     * to be combined via OR, set the same explicitly defined scope in all policies
     * using `add_filters_in_scope`.
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
     * @return string|NULL
     */
    protected function getFilterScope() : ?string
    {
        if ($this->filterScope === null && $this->metaObjectSelector !== null) {
            return $this->metaObjectSelector->__toString();
        }
        return $this->filterScope;
    }
    
    /**
     * Explicitly define a the filtering scope for this policy.
     * 
     * If multiple policies with filters are applied, the filters will be combined
     * via OR among policies with the same filtering scope and via AND if the scopes
     * are different. 
     * 
     * If not set explicitly, the scope is the target meta object of the policy.
     * 
     * For example, if there are policies, that allow a user to only see a certain
     * company, and policies, limiting the view to a country, a user that has
     * roles for Company1 and Company2 in Germany will receive the following filter:
     * `(Company = "Company1" OR Company = "Company2") AND Country = "Germany"`.
     * 
     * If you need the filters from multiple policies with different target objects 
     * to be combined via OR, set the same explicitly defined scope in all policies.
     * The scope can be any string, that is unique for the combination of apps you
     * are running on one workbench.
     * 
     * @uxon-property add_filters_in_scope
     * @uxon-type string
     * 
     * @param string $value
     * @return DataAuthorizationPolicy
     */
    protected function setAddFiltersInScope(string $value) : DataAuthorizationPolicy
    {
        $this->filterScope = $value;
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
     * Apply this policy (including possible filters) to these listed related objects too.
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
     *              "relation_path_from_policy_object": "ORDER"
     *          }, {
     *              "related_object": "my.App.TASK",
     *              "relation_path_from_policy_object": "TASK[OWNER_COMPANY]"}
     *          }
     *      ]
     *  }
     *  
     * ```
     * 
     * In this case, an employee of a copany will only be able to see orders placed by his company.
     * However, that user will still be able to see all order positions (`ORDERPOS`). To restrict 
     * those too, add
     * 
     * ```
     *          {
     *              "related_object": "my.App.ORDERPOS",
     *              "relation_path_from_policy_object": "ORDER__ORDERPOS"
     *          }
     *          
     * ```
     * 
     * Assuming each order position is linked to a `PRODUCT`, you may or may not want to limit visibility
     * of products. If you do, the user will not see any products, that were never ordered by his
     * company though!
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