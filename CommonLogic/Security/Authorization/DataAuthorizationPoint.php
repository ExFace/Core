<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Security\AuthorizationRuntimeError;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\CommonLogic\Security\Authorization\Obligations\DataFilterObligation;

/**
 * This authorization point allows to define policies for CRUD operations on DataSheets.
 * 
 * @method DataAuthorizationPolicy[] getPolicies()
 * 
 * @author Andrej Kabachnik
 *
 */
class DataAuthorizationPoint extends AbstractAuthorizationPoint
{
    const OPERATION_READ = 'read';
    const OPERATION_CREATE = 'create';
    const OPERATION_UPDATE = 'update';
    const OPERATION_DELETE = 'delete';
    
    private $unrestrictedCache = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::register()
     */
    protected function register() : AuthorizationPointInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeReadDataEvent::getEventName(), [$this, 'authorizeEvent']);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'authorizeEvent']);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'authorizeEvent']);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'authorizeEvent']);
        return $this;
    }
    
    /**
     * Checks authorization for an exface.Core.Actions.OnBeforeActionPerformed event.
     *
     * @param OnBeforeActionPerformedEvent $event
     * @return void
     */
    public function authorizeEvent(DataSheetEventInterface $event)
    {
        $operations = [];
        switch (true) {
            case $event instanceof OnBeforeReadDataEvent:
                $operations[] = self::OPERATION_READ;
                break;
            case $event instanceof OnBeforeCreateDataEvent:
                $operations[] = self::OPERATION_CREATE;
                break;
            case $event instanceof OnBeforeUpdateDataEvent:
                $operations[] = self::OPERATION_UPDATE;
                break;
            case $event instanceof OnBeforeDeleteDataEvent:
                $operations[] = self::OPERATION_DELETE;
                break;
        }
        $this->authorize($event->getDataSheet(), $operations);
        return;
    }
    
    /**
     * 
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::authorize()
     */
    public function authorize(DataSheetInterface $dataSheet = null, array $operations = [], UserImpersonationInterface $userOrToken = null) : ?DataSheetInterface
    {
        if ($this->isDisabled()) {
            return $dataSheet;
        }
        
        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
        
        if ($this->isCachedUnrestricted($dataSheet->getMetaObject(), $userOrToken, $operations)) {
            return $dataSheet;
        }
        
        $permissionsGenerator = $this->evaluatePolicies($dataSheet, $userOrToken, $operations);
        $decision = $this->evaluatePermissions($permissionsGenerator, $userOrToken, $dataSheet);
        
        // If permitted and there are no obligations, the permission is unrestricted, so we
        // don't need to re-check ist. Cache it here to avoid further authorization for this
        // object
        if ($decision->hasObligations() === false && $dataSheet !== null) {
            $this->setCacheUnrestricted($dataSheet->getMetaObject(), $userOrToken, $operations);
        }
        
        return $dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::evaluateObligations()
     */
    protected function evaluateObligations(PermissionInterface $permission, UserImpersonationInterface $userOrToken, $resource = null) : PermissionInterface
    {
        // Handle obligations
        $obligations = $permission->getObligations();
        $oblCnt = count($obligations);
        $dataSheet = $resource;
        if ($oblCnt > 0) {
            if (! $dataSheet instanceof DataSheetInterface) {
                throw new AuthorizationRuntimeError('Cannot fulfill obligations in data authorization point: provided resource (' . $resource === null ? 'null' : get_class($resource) . ') is not a data sheet!');
            }
            
            $oblCondGrp = ConditionGroupFactory::createOR($dataSheet->getMetaObject());
            $unfilteredObligationFound = false;
            foreach ($obligations as $obligation) {
                switch (true) {
                    case $obligation->isFulfilled():
                        break;
                    // Handle filter obligations
                    case $obligation instanceof DataFilterObligation:
                        // If the obligation has an empty condition group, the access is unrestricted
                        // regardless of any other filtering obligations: it is an `x OR true` then.
                        // In this case, make sure no filters are added and stop processing filtering
                        // obligations.
                        if ($unfilteredObligationFound === true || $obligation->getConditionGroup()->isEmpty()) {
                            $unfilteredObligationFound = true;
                            $obligation->setFulfilled(true);
                            break;
                        }
                        
                        if ($oblCnt === 1) {
                            $oblCondGrp = $obligation->getConditionGroup();
                        } else {
                            $oblCondGrp = $oblCondGrp->addNestedGroup($obligation->getConditionGroup());
                        }
                        $obligation->setFulfilled(true);
                        break;
                }
            }
            
            if (! $unfilteredObligationFound && ! $oblCondGrp->isEmpty()) {
                $dataSheet->setFilters($dataSheet->getFilters()->withAND($oblCondGrp));
            }
        }
        
        return parent::evaluateObligations($permission, $userOrToken, $resource);
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param UserImpersonationInterface $userOrToken
     * @param string[] $operations
     * @return DataAuthorizationPoint
     */
    protected function setCacheUnrestricted(MetaObjectInterface $object, UserImpersonationInterface $userOrToken, array $operations) : DataAuthorizationPoint
    {
        $key = $this->getCacheKey($object, $userOrToken);
        $cache = $this->unrestrictedCache[$key] ?? [];
        $cache = array_unique(array_merge($cache, $operations));
        $this->unrestrictedCache[$key] = $cache;
        return $this;
    }
    
    /**
     * 
     * @param MetaObjectInterface $object
     * @param UserImpersonationInterface $userOrToken
     * @return string
     */
    protected function getCacheKey(MetaObjectInterface $object, UserImpersonationInterface $userOrToken) : string
    {
        return $object->getId() . '_' . $userOrToken->getUsername();
    }
    
    protected function isCachedUnrestricted(MetaObjectInterface $object, UserImpersonationInterface $userOrToken, array $operations) : bool
    {
        $cache = $this->unrestrictedCache[$this->getCacheKey($object, $userOrToken)];
        // No cache means not unrestricted - need to evaluate policies!
        if (! is_array($cache)) {
            return false;
        }
        // Cache with empty operations array means fully unrestricted
        if (empty($cache)) {
            return true;
        }
        // Cache with non-empty operations means only certain operations are unrestricted
        return empty(array_diff($operations, $cache)) === true;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::addPolicy()
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface
    {
        $this->addPolicyInstance(new DataAuthorizationPolicy($this->getWorkbench(), $name, $effect, $targets, $condition));
        return $this;
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param UserImpersonationInterface $userOrToken
     * @return \Generator
     */
    protected function evaluatePolicies(DataSheetInterface $dataSheet, UserImpersonationInterface $userOrToken, array $operations = []) : \Generator
    {
        foreach ($this->getPolicies($userOrToken) as $policy) {
            yield $policy->authorize($userOrToken, $dataSheet, $operations);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::getResourceName()
     */
    protected function getResourceName($resource) : string
    {
        return "data of \"{$resource->getMetaObject()->getAliasWithNamespace()}\"";
    }
}