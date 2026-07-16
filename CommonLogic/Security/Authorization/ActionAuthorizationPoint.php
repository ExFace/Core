<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\Exceptions\Security\AccessPermissionDeniedError;
use exface\Core\Factories\PermissionFactory;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;

/**
 * 
 * 
 * @method ActionAuthorizationPolicy[] getPolicies()
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionAuthorizationPoint extends AbstractAuthorizationPoint
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::register()
     */
    protected function register() : AuthorizationPointInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeActionPerformedEvent::getEventName(), [$this, 'authorizeEvent']);
        return $this;
    }
    
    /**
     * Checks authorization for an exface.Core.Actions.OnBeforeActionPerformed event.
     *
     * @param OnBeforeActionPerformedEvent $event
     * @return void
     */
    public function authorizeEvent(OnBeforeActionPerformedEvent $event)
    {
        $authToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        $this->authorize($event->getAction(), $event->getTask(), $authToken);
        return;
    }
    
    /**
     * 
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::authorize()
     */
    public function authorize(ActionInterface $action = null, TaskInterface $task = null, UserImpersonationInterface $userOrToken = null) : ?TaskInterface
    {
        if ($this->isDisabled()) {
            return $task;
        }
        
        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
        
        $permissionsGenerator = $this->evaluatePolicies($action, $userOrToken, $task);
        $this->evaluatePermissions($permissionsGenerator, $userOrToken, $action);
        return $task;
    }

    /**
     * Returns an array of permission decisions - one for every input row of the given task
     * 
     * Edge cases:
     * - Returns an empty array if the authorization point is disabled
     * - Returns an array with a single permission if there is no input data or the input data has now rows
     * 
     * @param ActionInterface|null $action
     * @param TaskInterface|null $task
     * @param UserImpersonationInterface|null $userOrToken
     * @return PermissionInterface[]
     */
    public function authorizePerRow(ActionInterface $action = null, TaskInterface $task = null, UserImpersonationInterface $userOrToken = null) : array
    {
        if ($this->isDisabled()) {
            return [];
        }

        if (! $task->hasInputData() || $task->getInputData()->countRows() <= 1) {
            try {
                $this->authorize($action, $task, $userOrToken);
                return [PermissionFactory::createPermitted()];
            } catch (AccessPermissionDeniedError $e) {
                return [$e->getPermission()];
            }
        }

        if ($userOrToken === null) {
            $userOrToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        }
        
        $inputData = $task->getInputData();
        $policies = $this->getPolicies();
        $rowDecisions = [];
        foreach($policies as $policy) {
            // Make sure, the policies DO NOT NEED to read any additional data
            $inputData = $policy->prepareDataSheetToAuthorize($inputData);
        }
        $inputTpl = $inputData->copy()->removeRows();
        $decisions = [];
        // Obligation-based row tracking
        /*
        $permissionsGenerator = $this->evaluatePolicies($action, $userOrToken, $task);
        $decision = $this->evaluatePermissions($permissionsGenerator, $userOrToken, $action);
        foreach ($decision->getObligations() as $obligation) {
            //
            // [
            //  0 => [ Allow, Deny ],
            //  1 => [ Allow ],
            //  2 => [ ]
            // ]
            //
            $decisionsPerRow[$obligation->getRowIndex()][] = $obligation->getDecision();
        }
        $rowDecisions = [];
        foreach ($decisionsPerRow as $rowPermissions) {
            $rowDecisions[] = new CombinedPermission(PolicyCombiningAlgorithmDataType::PERMIT_UNLESS_DENY, $rowPermissions);
        }
        */
        
        /* real per-row authorization
        foreach ($inputData->getRows() as $row) {
            $inputTpl->addRow($row, false, false);

            $permissionsGenerator = $this->evaluatePolicies($action, $userOrToken, $task);
            try {
                $decision = $this->evaluatePermissions($permissionsGenerator, $userOrToken, $action);
                $decisions[] = $decision;
            } catch (AccessPermissionDeniedError $e) {

            }
        }*/
        return $rowDecisions;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::addPolicy()
     */
    public function addPolicy(array $targets, PolicyEffectDataType $effect, string $name = '', UxonObject $condition = null) : AuthorizationPointInterface
    {
        $this->addPolicyInstance(new ActionAuthorizationPolicy($this->getWorkbench(), $name, $effect, $targets, $condition));
        return $this;
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param UserImpersonationInterface $userOrToken
     * @return \Generator
     */
    protected function evaluatePolicies(ActionInterface $action, UserImpersonationInterface $userOrToken, TaskInterface $task = null) : \Generator
    {
        foreach ($this->getPolicies($userOrToken) as $policy) {
            yield $policy->authorize($userOrToken, $action, $task);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authorization\AbstractAuthorizationPoint::getResourceName()
     */
    protected function getResourceName($resource) : string
    {
        return "action \"{$resource->getAliasWithNamespace()}\"";
    }
}