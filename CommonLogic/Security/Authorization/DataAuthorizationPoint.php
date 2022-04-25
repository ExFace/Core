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
        
        $permissionsGenerator = $this->evaluatePolicies($dataSheet, $userOrToken, $operations);
        $this->combinePermissions($permissionsGenerator, $userOrToken, $dataSheet);
        return $dataSheet;
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