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

/**
 * Policy for access to actions.
 * 
 * Possible targets:
 * 
 * - User group
 * - Object action - policy only applies to this particular action model
 * - Action prototype - policy applies to all actions of this prototype
 * - Meta object - policy applies to all actions on this meta object
 * 
 * Additional conditions:
 * 
 * - `command_line_task` - if set, policy only applies to CLI tasks (`true`) 
 * or web tasks (`false`)
 * - `action_trigger_widget_match` - if set, policy only applies if the task has
 * a trigger widget and the action matches that widget's action (`true`) or not 
 * (`false`). NOTE: such policies never apply to actions, that explicitly do not 
 * require a trigger widget - e.g. `exface.Core.Login` or similar.
 * - `exclude_actions` - list of action selectors not to apply this policy to
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
    
    private $conditionUxon = null;
    
    private $effect = null;
    
    private $actionTriggerWidgetMatch = null;
    
    private $excludeActionSelectors = [];
    
    private $cliTasks = null;
    
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
        if ($str = $targets[PolicyTargetDataType::ACTION]) {
            $this->actionSelector =  new ActionSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::META_OBJECT]) {
            $this->metaObjectSelector = new MetaObjectSelector($this->workbench, $str);
        }        
        if ($str = $targets[PolicyTargetDataType::PAGE_GROUP]) {
            $this->pageGroupSelector = new UiPageGroupSelector($this->workbench, $str);
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
            
            // Match action
            if (($selector = $this->actionSelector) !== null) {
                switch(true) {
                    case $selector->isFilepath():
                        $selectorClassPath = StringDataType::substringBefore($selector->toString(), '.' . FileSelectorInterface::PHP_FILE_EXTENSION);
                        $actionClassPath = FilePathDataType::normalize(get_class($action));
                        $applied = $selectorClassPath === $actionClassPath;
                        break;
                    case $selector->isClassname():
                        $applied = trim(get_class($action), "\\") === trim($selector->toString(), "\\");
                        break;
                    case $selector->isAlias():
                        $applied = $action->getAliasWithNamespace() === $selector->toString();
                        break;
                }
                if ($applied === false) {
                    return PermissionFactory::createNotApplicable($this);
                }
            } else {
                $applied = true;
            }
            
            // See if applicable only to cli/non-cli tasks
            if (($expectCli = $this->getCommandLineTaskRestriction()) !== null) {
                $isCli = ($task instanceof CliTaskInterface);
                switch (true) {
                    case $expectCli === true && $isCli === false:
                    case $expectCli === false && $isCli === true:
                        return PermissionFactory::createNotApplicable($this);
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
                return PermissionFactory::createNotApplicable($this);
            } else {
                $applied = true;
            }
            
            // See if trigger widget must be validatable
            if ($this->getActionTriggerWidgetMatch() !== null) {
                // If the specific action does not require a trigger widget,
                // don't apply the policy nevertheless
                if ($action->isTriggerWidgetRequired() === false) {
                    return PermissionFactory::createNotApplicable($this);
                }
                $triggerRequired = $this->getActionTriggerWidgetMatch();
                $triggerValidated = $this->isActionTriggerWidgetValid($action, $task);
                switch (true) {
                    case $triggerRequired === true && $triggerValidated === false:
                    case $triggerRequired === false && $triggerValidated === true:
                        return PermissionFactory::createNotApplicable($this);
                    default:
                        $applied = true;
                }
            }
            
            // Match meta object
            if ($this->metaObjectSelector !== null) {
                $object = $action->getMetaObject();
                if ($object === null || $object->is($this->metaObjectSelector) === false) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            // Match page
            if ($this->pageGroupSelector !== null) {
                if ($action !== null && $action->isDefinedInWidget()) {
                    $page = $action->getWidgetDefinedIn()->getPage();
                } elseif ($task !== null && $task->isTriggeredOnPage()) {
                    $page = $task->getPageTriggeredOn();
                } else {
                    $page = null;
                }
                
                if ($page->isInGroup($this->pageGroupSelector) === false) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            foreach ($this->getExcludeActions() as $selector) {
                if ($action->isExactly($selector)) {
                    return PermissionFactory::createNotApplicable($this);
                }
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this);
            }
        } catch (\Throwable $e) {
            $action->getWorkbench()->getLogger()->logException($e);
            return PermissionFactory::createIndeterminate($e, $this->getEffect(), $this);
        }
        
        // If all targets are applicable, the permission is the effect of this condition.
        return PermissionFactory::createFromPolicyEffect($this->getEffect(), $this);
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
            if ($action->isExactly(ShowWidget::class) && $task->isTriggeredOnPage()) {
                return true;
            }
            if ($action->isExactly(ReadPrefill::class) && $task->isTriggeredByWidget()) {
                return true;
            }
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
                    case $widgetAction instanceof iCallOtherActions:
                        foreach ($widgetAction->getActions() as $chainedAction) {
                            if ($chainedAction === $action) {
                                return true;
                            }
                        }
                        return false;
                    default:
                        return $widgetAction === $action;
                }
            }
        }
        
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
}