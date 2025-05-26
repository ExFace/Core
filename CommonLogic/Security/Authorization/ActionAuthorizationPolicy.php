<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\CommonLogic\Model\ExistsCondition;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
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
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\CommonLogic\Selectors\UiPageGroupSelector;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;
use exface\Core\Interfaces\Tasks\CliTaskInterface;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Actions\ShowWidget;
use exface\Core\Actions\ReadPrefill;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\CommonLogic\Tasks\ScheduledTask;
use exface\Core\Exceptions\Security\AuthorizationRuntimeError;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Exceptions\Security\AccessDeniedError;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\CommonLogic\Selectors\FacadeSelector;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;

/**
 * Policy for access to actions.
 * 
 * Possible targets:
 * 
 * - User role - policy applies to users with this role only
 * - Object action - policy only applies to this particular action model
 * - Action prototype - policy applies to all actions of this prototype
 * - Meta object - policy applies to all actions on this meta object
 * - App - policy applies to actions, objects or pages of this app only - see details below.
 * 
 * **NOTE:** It is important to understand, that policies targeting an app
 * can have different effects depending on what apps are taken into account
 * at the moment an action is performed. 
 * 
 * By default, a policy targeting an app will be applied to all actions, that belong
 * to that app - regardless of the object they are performed upon or the page they
 * are triggered in. However, you can customize this behavior using the following 
 * additional conditions:
 * 
 * - `apply_if_target_app_matches_action_app` - default - means, a policy targeting an app is
 * applied to actions, that belong to that app.
 * - `apply_if_target_app_matches_object_app` - means, a policy targeting
 * an app is applied to actions, performed upon objects of that app.
 * - `apply_if_target_app_matches_page_app` - means, a policy targeting an app is
 * applied to actions performed on pages of that app
 * 
 * Additional conditions:
 * 
 * - `command_line_task`, `http_task`, `scheduler_task` - if set, the policy only 
 * applies to the respective task type (`true`) or is explicitly not applicable 
 * to it (`false`).
 * - `action_trigger_widget_match` - if set, policy only applies if the task has
 * a trigger widget and the action matches that widget's action (`true`) or not 
 * (`false`). NOTE: such policies never apply to actions, that explicitly do not 
 * require a trigger widget - e.g. `exface.Core.Login` or similar.
 * - `exclude_actions` - list of action selectors not to apply this policy to
 * - `apply_if` - conditions (filters) to evaluate when authorizing - the policy
 * will become inapplicable if these condition evaluate to FALSE.
 * - `apply_if_exists` - a data sheet, that will be read when authorizing - the
 * policy will become inapplicable if it is empty.
 *  - `apply_if_not_exists` - a data sheet, that will be read when authorizing - the
 *  policy will become inapplicable if it is NOT empty.
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionAuthorizationPolicy implements AuthorizationPolicyInterface
{
    use ImportUxonObjectTrait;

    private $workbench = null;
    private $name = '';
    private $userRoleSelector = null;
    private $actionSelector = null;
    private $metaObjectSelector = null;
    private $pageGroupSelector = null;
    private $facadeSelector = null;
    private $appUid = null;
    private $conditionUxon = null;
    private $effect = null;
    private $actionTriggerWidgetMatch = null;
    private $excludeActionSelectors = [];
    private $cliTasks = null;
    private $scheduledTasks = null;
    private $httpTasks = null;
    private $appUidAppliesToAction = true;
    private $appUidAppliesToObject = false;
    private $appUidAppliesToPage = false;
    private $applyIfUxon = null;
    private $applyIfConditionGroup = null;
    private $applyIfExistsUxon = null;
    private $applyIfExists = null;
    private $applyIfNotExistsUxon = null;
    private $applyIfNotExists = null;
    
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
        if (null !== $str = $targets[PolicyTargetDataType::ACTION]) {
            $this->actionSelector =  new ActionSelector($this->workbench, $str);
        }
        if (null !== $str = $targets[PolicyTargetDataType::META_OBJECT]) {
            $this->metaObjectSelector = new MetaObjectSelector($this->workbench, $str);
        }        
        if (null !== $str = $targets[PolicyTargetDataType::PAGE_GROUP]) {
            $this->pageGroupSelector = new UiPageGroupSelector($this->workbench, $str);
        }
        if (null !== $str = $targets[PolicyTargetDataType::APP]) {
            $this->appUid = $str;
        }
        if (null !== $str = $targets[PolicyTargetDataType::FACADE]) {
            $this->facadeSelector =  new FacadeSelector($this->workbench, $str);
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
    public function authorize(UserImpersonationInterface $userOrToken = null, ActionInterface $action = null, TaskInterface $task = null): PermissionInterface
    {
        $applied = false;
        
        try {
            if ($action === null) {
                throw new InvalidArgumentException('Cannot evalute action access policy: no action provided!');
            }
            
            // Stop early if action explicitly excluded
            foreach ($this->getExcludeActions() as $selector) {
                if ($action->isExactly($selector)) {
                    return PermissionFactory::createNotApplicable($this, 'Action excluded explicitly');
                }
            }
            
            // Match action
            if (null !== $selector = $this->actionSelector) {
                switch(true) {
                    case $selector->isFilepath():
                        $selectorClassPath = StringDataType::substringBefore($selector->toString(), '.' . FileSelectorInterface::PHP_FILE_EXTENSION);
                        $actionClassPath = FilePathDataType::normalize(get_class($action));
                        $applied = strcasecmp($selectorClassPath, $actionClassPath) === 0;
                        break;
                    case $selector->isClassname():
                        $applied = trim(get_class($action), "\\") === trim($selector->toString(), "\\");
                        break;
                    case $selector->isAlias():
                        $applied = $action->getAliasWithNamespace() === $selector->toString();
                        break;
                }
                if ($applied === false) {
                    return PermissionFactory::createNotApplicable($this, 'Action does not match');
                }
            } else {
                $applied = true;
            }
            
            // Match facade
            if (null !== $this->facadeSelector) {
                if ($task !== null && null !== $facade = $task->getFacade()) {
                    if ($facade->isExactly($this->facadeSelector) === true) {
                        $applied = true;
                    } else {
                        return PermissionFactory::createNotApplicable($this, 'Facade does not match');
                    }
                } else {
                    return PermissionFactory::createNotApplicable($this, 'Facade required, but cannot be determined');
                }
            }
            
            // See if applicable only to cli/non-cli tasks
            if (($expectCli = $this->getCommandLineTaskRestriction()) !== null) {
                $isCli = ($task instanceof CliTaskInterface);
                switch (true) {
                    case $expectCli === true && $isCli=== false:
                    case $expectCli === false && $isCli === true:
                        return PermissionFactory::createNotApplicable($this, 'CLI restriction (`command_line_task`)');
                    default:
                        $applied = true;
                }
            }
            
            // See if applicable only to http/non-http tasks
            if (($expectHttp = $this->getHttpTaskRestriction()) !== null) {
                $isHttp = ($task instanceof HttpTaskInterface);
                switch (true) {
                    case $expectHttp === true && $isHttp === false:
                    case $expectHttp === false && $isHttp === true:
                        return PermissionFactory::createNotApplicable($this, 'HTTP restriction (`http_task`)');
                    default:
                        $applied = true;
                }
            }
            
            // See if applicable only to scheduler/non-scheduler tasks
            if (($expectScheduler = $this->getSchedulerTaskRestriction()) !== null) {
                $isScheduler = ($task instanceof ScheduledTask);
                switch (true) {
                    case $expectScheduler === true && $isScheduler === false:
                    case $expectScheduler === false && $isScheduler === true:
                        return PermissionFactory::createNotApplicable($this, 'Scheduler restriction (`scheduler_task`)');
                    default:
                        $applied = true;
                }
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
            
            // See if trigger widget must be validatable
            if ($this->getActionTriggerWidgetMatch() !== null) {
                // If the specific action does not require a trigger widget,
                // don't apply the policy nevertheless
                if ($action->isTriggerWidgetRequired() === false) {
                    return PermissionFactory::createNotApplicable($this, 'Widget match restriction (`action_trigger_widget_match`) set, but action does not require a trigger widget');
                }
                $triggerRequired = $this->getActionTriggerWidgetMatch();
                $triggerValidated = $this->isActionTriggerWidgetValid($action, $task);
                switch (true) {
                    case $triggerRequired === true && $triggerValidated === false:
                    case $triggerRequired === false && $triggerValidated === true:
                        return PermissionFactory::createNotApplicable($this, 'Widget match restriction (`action_trigger_widget_match`)');
                    default:
                        $applied = true;
                }
            }
            
            // Match meta object
            if ($this->metaObjectSelector !== null) {
                $object = $this->findObject($action);
                if ($object === null) {
                    return PermissionFactory::createNotApplicable($this, 'Meta object required, but action has none');
                }
                if ($object->is($this->metaObjectSelector) === false) {
                    return PermissionFactory::createNotApplicable($this, 'Meta object does not match');
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            // Match additional conditions
            // IDEA added placeholders for input data???
            if ($this->hasApplyIf() === true) {
                $object = $object ?? $this->findObject($action);
                $conditionGrp = $this->getApplyIf($object);
                if ($task !== null && $task->hasInputData()) {
                    if ($conditionGrp->evaluate($task->getInputData()) === false) {
                        return PermissionFactory::createNotApplicable($this, 'Condition `apply_if` not matched by action input data');
                    } else {
                        $applied = true;
                    }
                } else {
                    if ($conditionGrp->evaluate() === false) {
                        return PermissionFactory::createNotApplicable($this, 'Condition `apply_if` not matched');
                    } else {
                        $applied = true;
                    }
                }
            }

            // Match apply_if_exists
            if (null !== $condition = $this->getApplyIfExists()) {
                if ($task && $task->hasInputData()) {
                    $inputData = $task->getInputData();
                    if (! $condition->evaluate($inputData)) {
                        return PermissionFactory::createNotApplicable($this, 'Condition `apply_if_exists` not matched by action input data');
                    } else {
                        $applied = true;
                    }
                }
            }

            // Match apply_if_exists
            if (null !== $condition = $this->getApplyIfNotExists()) {
                if ($task && $task->hasInputData()) {
                    $inputData = $task->getInputData();
                    if ($condition->evaluate($inputData)) {
                        return PermissionFactory::createNotApplicable($this, 'Condition `apply_if_not_exists` matched by at least one row of action input data');
                    } else {
                        $applied = true;
                    }
                }
            }
            
            // Match page
            if ($this->pageGroupSelector !== null) {            
                $page = $this->findPage($action, $task);
                if ($page !== null && $page->isInGroup($this->pageGroupSelector) === false) {
                    return PermissionFactory::createNotApplicable($this, 'Page group does not match');
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            // Match app
            if ($this->appUid !== null && $action !== null) {
                $appMatch = null;
                $appApplicableTo = '';
                if ($this->appUidAppliesToAction === true) {
                    $appApplicableTo .= ($appApplicableTo !== '' ? ', ' : '') . 'action';
                    if (strcasecmp($action->getApp()->getUid(), $this->appUid) === 0) {
                        $appMatch = 'action';
                    }
                }
                if ($this->appUidAppliesToObject === true) {
                    $appApplicableTo .= ($appApplicableTo !== '' ? ', ' : '') . 'object';
                    $object = $this->findObject($action);
                    if ($object !== null && strcasecmp($object->getApp()->getUid(), $this->appUid) === 0) {
                        $appMatch = 'object';
                    }
                }
                if ($this->appUidAppliesToPage === true) {
                    $appApplicableTo .= ($appApplicableTo !== '' ? ', ' : '') . 'page';
                    $page = $this->findPage($action, $task);
                    if ($page !== null && $page->hasApp() && strcasecmp($page->getApp()->getUid(), $this->appUid) === 0) {
                        $appMatch = 'page';
                    }
                }
                
                if ($appMatch === null) {
                    return PermissionFactory::createNotApplicable($this, 'App does not match ' . $appApplicableTo);
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this, 'No targets or conditions matched');
            }
        } catch (AuthorizationExceptionInterface | AccessDeniedError $e) {
            $action->getWorkbench()->getLogger()->logException($e);
            return PermissionFactory::createDenied($this, $e->getMessage());
        } catch (\Throwable $e) {
            $action->getWorkbench()->getLogger()->logException(new AuthorizationRuntimeError('Indeterminate permission for policy "' . $this->getName() . '" due to error: ' . $e->getMessage(), null, $e));
            return PermissionFactory::createIndeterminate($e, $this->getEffect(), $this);
        }
        
        // If all targets are applicable, the permission is the effect of this condition.
        return PermissionFactory::createFromPolicyEffect($this->getEffect(), $this);
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param TaskInterface $task
     * @return UiPageInterface|NULL
     */
    protected function findPage(ActionInterface $action = null, TaskInterface $task = null) : ?UiPageInterface
    {
        if ($action !== null && $action->isDefinedInWidget()) {
            $page = $action->getWidgetDefinedIn()->getPage();
        } elseif ($task !== null && $task->isTriggeredOnPage()) {
            $page = $task->getPageTriggeredOn();
        } else {
            $page = null;
        }
        return $page;
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @return MetaObjectInterface|NULL
     */
    protected function findObject(ActionInterface $action) : ?MetaObjectInterface
    {
        try {
            $object = $action->getMetaObject();
        } catch (ActionObjectNotSpecifiedError $e) {
            return null;
        }
        return $object;
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
     * Only apply this policy if the action is defined in the same widget it is called from (or not).
     * 
     * This only has effect on action prototypes, that require a trigger widget
     * by default.
     * 
     * By default, policies do not check, if their actions are defined in a widget,
     * if that widget exists and if it's really the widget, that calls the action.
     * This option allows to explicitly address actions getting called from the
     * widget they are defined in (= `true`) and action that are not defined in
     * any widget (= `false`) although the action prototype normally requires a
     * trigger widget.
     * 
     * **NOTE** there are some exceptions:
     * 
     * - action `ShowWidget` can be performed for every widget regardless of it's trigger
     * - action `ReadPrefill` can be performed for every widget regardless of it's trigger
     * 
     * @uxon-property action_trigger_widget_match
     * @uxon-type boolean
     * 
     * @param bool $trueOrFalse
     * @return ActionAuthorizationPolicy
     */
    protected function setActionTriggerWidgetMatch(bool $trueOrFalse) : ActionAuthorizationPolicy
    {
        $this->actionTriggerWidgetMatch = $trueOrFalse;
        return $this;
    }
    
    /**
     * @deprecated use setActionTriggerWidgetMatch() instead!
     */
    protected function setActionTriggerPageKnown(bool $trueOrFalse) : ActionAuthorizationPolicy
    {
        return $this->setActionTriggerWidgetMatch($trueOrFalse);
    }
    
    /**
     * 
     * @return bool|NULL
     */
    protected function getActionTriggerWidgetMatch() : ?bool
    {
        return $this->actionTriggerWidgetMatch;
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param TaskInterface $task
     * @return bool
     */
    protected function isActionTriggerWidgetValid(ActionInterface $action, TaskInterface $task = null) : bool
    {
        if ($task) {
            // If the action shows a widget, don't bother - the PageAuthorizationPoint will do
            if ($action->isExactly(ShowWidget::class) && $task->isTriggeredOnPage()) {
                return true;
            }
            // If the action prefills a widget, don't bother - if the widget is visible, we can do the prefill
            if ($action->isExactly(ReadPrefill::class) && $task->isTriggeredByWidget()) {
                return true;
            }
            
            // If we know, what triggered the task, see if the action defined in the trigger matches the action
            // we received with the task
            if ($task->isTriggeredByWidget()) {
                $triggerWidget = $task->getWidgetTriggeredBy();
                if (! ($triggerWidget instanceof iTriggerAction)) {
                    return false;
                }
                if (! $triggerWidget->hasAction()) {
                    return false;
                }
                $widgetAction = $triggerWidget->getAction();
                
                switch (true) {
                    // In case of action chains and other actions that call other actions, we need to check
                    // the action itself and its subactions as both are allowed to be called!
                    case $widgetAction instanceof iCallOtherActions:
                        if ($widgetAction === $action) {
                            return true;
                        }
                        if ($widgetAction->containsAction($action) === true) {
                            return true;
                        }
                        return false;
                    // In all other cases, the check if the action of the widget is really the action being called
                    default:
                        return $widgetAction === $action;
                }
            }
        }
        
        // At this point, we know, the task had no trigger, so this would only allow actions that do not originate from
        // a widget (otherwise the task would have a trigger, wouldn't it?)
        if ($action->isDefinedInWidget()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Make this policy not applicable to one or more specific actions (exact matches!).
     * 
     * @uxon-property exclude_actions
     * @uxon-type metamodel:action
     * @uxon-template [""]
     * 
     * @param UxonObject $excludes
     * @return ActionAuthorizationPolicy
     */
    protected function setExcludeActions(UxonObject $excludes) : ActionAuthorizationPolicy
    {
        foreach ($excludes->getPropertiesAll() as $selectorString) {
            $this->excludeActionSelectors[] = new ActionSelector($this->workbench, $selectorString);
        }
        return $this;
    }
    
    /**
     * 
     * @return ActionSelectorInterface[]
     */
    protected function getExcludeActions() : array
    {
        return $this->excludeActionSelectors;
    }
    
    /**
     * Set to TRUE to apply only to command line tasks or to FALSE to exclude CLI tasks.
     * 
     * By default, the policy will be applied to all tasks regardless of their origin.
     * 
     * @uxon-property command_line_task
     * @uxon-type boolean
     * 
     * @param bool $trueOrFalse
     * @return ActionAuthorizationPolicy
     */
    protected function setCommandLineTask(bool $trueOrFalse) : ActionAuthorizationPolicy
    {
        $this->cliTasks = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool|NULL
     */
    protected function getCommandLineTaskRestriction() : ?bool
    {
        return $this->cliTasks;
    }
    
    /**
     * Set to TRUE to apply only to scheduler tasks or to FALSE to exclude scheduler tasks.
     *
     * By default, the policy will be applied to all tasks regardless of their origin.
     *
     * @uxon-property scheduler_task
     * @uxon-type boolean
     *
     * @param bool $trueOrFalse
     * @return ActionAuthorizationPolicy
     */
    protected function setSchedulerTask(bool $trueOrFalse) : ActionAuthorizationPolicy
    {
        $this->scheduledTasks = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @return bool|NULL
     */
    protected function getSchedulerTaskRestriction() : ?bool
    {
        return $this->scheduledTasks;
    }
    
    /**
     * Set to TRUE to apply only to HTTP tasks or to FALSE to exclude HTTP tasks.
     *
     * By default, the policy will be applied to all tasks regardless of their origin.
     *
     * @uxon-property http_task
     * @uxon-type boolean
     *
     * @param bool $trueOrFalse
     * @return ActionAuthorizationPolicy
     */
    protected function setHttpTask(bool $trueOrFalse) : ActionAuthorizationPolicy
    {
        $this->httpTasks = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @return bool|NULL
     */
    protected function getHttpTaskRestriction() : ?bool
    {
        return $this->httpTasks;
    }
    
    protected function getApplyIfTargetAppMatchesActionApp() : bool
    {
        return $this->appUidAppliesToAction;
    }
    
    /**
     * Set to TRUE to apply policies with an app as target to actions, that belong to that app
     * 
     * @uxon-property apply_if_target_app_matches_action_app
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return ActionAuthorizationPolicy
     */
    protected function setApplyIfTargetAppMatchesActionApp(bool $value) : ActionAuthorizationPolicy
    {
        $this->appUidAppliesToAction = $value;
        return $this;
    }
    
    protected function getApplyIfTargetAppMatchesObjectApp() : bool
    {
        return $this->appUidAppliesToObject;
    }
    
    /**
     * Set to TRUE to apply policies with an app as target to actions, dealing with an object of that app
     *
     * @uxon-property apply_if_target_app_matches_object_app
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return ActionAuthorizationPolicy
     */
    protected function setApplyIfTargetAppMatchesObjectApp(bool $value) : ActionAuthorizationPolicy
    {
        $this->appUidAppliesToObject = $value;
        return $this;
    }
    
    protected function getApplyIfTargetAppMatchesPageApp() : bool
    {
        return $this->appUidAppliesToObject;
    }
    
    /**
     * Set to TRUE to apply policies with an app as target to actions, called on a page of that app
     *
     * @uxon-property apply_if_target_app_matches_page_app
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return ActionAuthorizationPolicy
     */
    protected function setApplyIfTargetAppMatchesPageApp(bool $value) : ActionAuthorizationPolicy
    {
        $this->appUidAppliesToObject = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasApplyIf() : bool
    {
        return $this->applyIfUxon !== null;
    }
    
    /**
     * 
     * @return ConditionGroupInterface|NULL
     */
    protected function getApplyIf(MetaObjectInterface $baseObject) : ?ConditionGroupInterface
    {
        if ($this->applyIfConditionGroup === null && $this->applyIfUxon !== null) {
            $this->applyIfConditionGroup = ConditionGroupFactory::createFromUxon($this->workbench, $this->applyIfUxon, $baseObject);
        }
        return $this->applyIfConditionGroup;
    }
    
    /**
     * Only apply this policy if the provided condition is matched
     * 
     * If `apply_if` is defined, the policy will be applied if the condition resolves to `true` or
     * will produce a `not applicable` result if it doesn't.
     *
     * @uxon-property apply_if
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "==","value": ""}]}
     *
     * @param UxonObject $value
     * @return AbstractAuthorizationPoint
     */
    protected function setApplyIf(UxonObject $value) : ActionAuthorizationPolicy
    {
        $this->applyIfUxon = $value;
        $this->applyIfConditionGroup = null;
        return $this;
    }

    /**
     * @return ExistsCondition|null
     */
    protected function getApplyIfExists() : ?ExistsCondition
    {
        if ($this->applyIfExists === null && $this->applyIfExistsUxon !== null) {
            $this->applyIfExists = new ExistsCondition($this->workbench, $this->applyIfExistsUxon);;
        }
        return $this->applyIfExists;
    }

    /**
     * Only apply this policy if at least on row of data defined here exists for every input row of the action
     *
     * Here you can define a data sheet to check if this policy is applicable to a specific row of input data.
     * You can use placeholders to include values from the input data in the filters of the lookup-sheet.
     *
     * For example, this can be used to build access control lists (ACLs) for a `PRODUCT` object. Assume, we have
     * a table `PRODUCT_ACL` with `USER_ROLE`, `CATEGORY` and `MANAGER_GROUP`. It sais, that a user role can edit
     * product data if there is an entry in the ACL table for that role and at least one category of that product.
     *
     * However, some products are explicitly managed by a `MANAGER_GROUP`. So in our ACL table we can make some of
     * the rules apply only if the product belongs to a certain manager group. Technically, this means, the ACL
     * rule will apply if it has no `MANAGER_GROUP` or the product has the same group as the rule.
     *
     * Here is how to create a corresponding allowing permission. The placeholders in the values refer to the
     * input data of our action - in this example the input data is assumed to be based on `PRODUCT`.
     *
     * ```
     * {
     *  "apply_if_exists": {
     *      "data_sheet": {
     *          "object_alias": "my.App.PRODUCT_ACL",
     *          "filters": {
     *              "operator": "AND",
     *              "conditions": [
     *                  {"expression": "CATEGORY", "comparator": "[", "value": "[#PRODUCT_CATEGORIES__CATEGORY:LIST_DISTINCT#]"},
     *                  {"expression": "ROLE", "comparator": "[", "value": "=User('USER_ROLE_USERS__USER_ROLE:LIST_DISTINCT')"}
     *              ],
     *              "nested_groups": [
     *                  {
     *                      "operator": "OR",
     *                      "conditions": [
     *                          {"expression": "MANAGER_GROUP", "comparator": "==", "value": ""},
     *                          {"expression": "MANAGER_GROUP", "comparator": "==", "value": "[#MANAGER_GROUP#]"}
     *                      ]
     *                  }
     *              ]
     *          }
     *      }
     *  }
     * }
     *
     * ```
     *
     * @uxon-property apply_if_exists
     * @uxon-type \exface\Core\CommonLogic\Model\ExistsCondition
     * @uxon-template {"data_sheet": {"object_alias": "", "filters": {"operator": "AND", "conditions": [{"expression": "", "comparator": "==", "value": ""}]}}}
     *
     * @uxon-placeholder [#<metamodel:attribute>#]
     * @uxon-placeholder [#<metamodel:formula>#]
     *
     * @return ExistsCondition|null
     */
    protected function setApplyIfExists(UxonObject $uxon) : ActionAuthorizationPolicy
    {
        $this->applyIfExistsUxon = $uxon;
        return $this;
    }

    /**
     * @return ExistsCondition|null
     */
    protected function getApplyIfNotExists() : ?ExistsCondition
    {
        if ($this->applyIfNotExists === null && $this->applyIfNotExistsUxon !== null) {
            $this->applyIfNotExists = new ExistsCondition($this->workbench, $this->applyIfNotExistsUxon);;
        }
        return $this->applyIfNotExists;
    }

    /**
     * Only apply this policy if no row of data defined here exists for any input row of the action
     *
     * This is the exact opposite of `apply_if_exists`. It uses the same logic, but makes the policy
     * inapplicable if anything is found.
     *
     * @uxon-property apply_if_exists
     * @uxon-type \exface\Core\CommonLogic\Model\ExistsCondition
     * @uxon-template {"data_sheet": {"object_alias": "", "filters": {"operator": "AND", "conditions": [{"expression": "", "comparator": "==", "value": ""}]}}}
     *
     * @return ExistsCondition|null
     */
    protected function setApplyIfNotExists(UxonObject $uxon) : ActionAuthorizationPolicy
    {
        $this->applyIfNotExistsUxon = $uxon;
        return $this;
    }
}