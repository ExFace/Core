<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Debugger\LogBooks\DataLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\Traits\ICanBypassDataAuthorizationTrait;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\TaskFactory;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Events\DataTransactionEventInterface;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Interfaces\Events\TaskEventInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Interfaces\Tasks\ResultDataInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use Throwable;

/**
 * Calls an action when an event ist triggered for the behaviors object
 * 
 * Being attached to a meta object, this behavior will trigger its action every time something happens
 * to its object.
 * 
 * You can make the behavior listen to one `event_alias` or event multiple `event_aliases`. Additionally
 * you can define configure it to call the action only on certain conditions via `only_if_attributes_change` 
 * and `only_if_data_matches_conditions`.
 * 
 * It is also possible to make this behavior replace the original action, that triggers it. Use `event_prevent_default`
 * to prevent the operation of on-before events.
 * 
 * ## Transaction handling and errors
 * 
 * By default, the action is performed within the same transaction as the original event (only if the
 * event actually has a transaction, of course - like for data sheet events). However, you can explicitly
 * commit the transaction before calling the action via `commit_before_action`.
 * 
 * Being part of the transaction also implies, that any error in the called action will roll back the
 * entire transaction. If that is unwanted, but you do not want to commit the transaction either, set
 * `error_if_action_fails` to FALSE.
 * 
 * ## Permissions and data authorization
 * 
 * By default, the called action will adhere to all authorization policies - just like when it would be
 * called by the current user directly. 
 * 
 * However, there may be cases, when the action is supposed to run without any restrictions - for example,
 * if the behavior should ensure, that things are written in the background, that the user does not have
 * access to. Use `bypass_data_authorization_point` in this case to use elevated data access.
 * 
 * A common use case for this feature is reading missing data for some internal logic after the user has
 * explicitly passed a business object out of his scope. E.g. passing an order to the next department would
 * make it inaccessible for the current user while there still might be some on-update-behaviors that
 * certainly need to read/write the data of this order.
 * 
 * ## Examples
 * 
 * ### Call an ection every time an instance of this object is created or updated
 * 
 * ```
 * {
 *  "event_aliass": [
 *      "exface.Core.DataSheet.OnBeforeCreateData",
 *      "exface.Core.DataSheet.OnBeforeUpdateData"
 *  ],
 *  "action": {
 *      "alias": "..."
 *  }
 * }
 * 
 * ```
 * 
 * ### Log data every time the state of a document changes to "30"
 * 
 * ```
 *  {
 *      "event_alias": "exface.Core.DataSheet.OnBeforeUpdateData",
 *      "only_if_attributes_change": ["STATE"],
 *      "only_if_data_matches_conditions": {
 *          "operator": "AND",
 *          "conditions": [
 *              {"expression": "STATE", "comparator": "==", "value": 30}
 *          ]
 *      },
 *      "action": {
 *          "alias": "exface.Core.CreateData",
 *          "object_alias": "my.App.DOC_STATE_LOG",
 *          "input_mapper": {
 *              "from_object_alias": "my.App.DOC",
 *              "to_object_alias": my.App.DOC_STATE_LOG",
 *              "column_to_column_mappings": [
 *                  {"from": "...", "to": "..."},
 *              ]
 *          }
 *      }
 *  }
 * 
 * ```
 * 
 * @author SFL
 * @author Andrej Kabachnik
 *
 */
class CallActionBehavior extends AbstractBehavior
{
    const PREVENT_DEFAULT_ALWAYS = 'always';
    
    const PREVENT_DEFAULT_NEVER = 'never';
    
    const PREVENT_DEFAULT_IF_ACTION_CALLED = 'if_action_called';

    use ICanBypassDataAuthorizationTrait;
    
    private $eventAliases = [];
    
    private $eventPreventDefault = null;

    private $action = null;
    
    private $actionConfig = null;
    
    private $onlyIfAttributesChange = [];
    
    private $onlyIfDataMatchesConditionGroupUxon = null;
    
    private $ignoreDataSheets = [];
	
	private $ignoreLogbooks = [];
    
    private $onFailError = true;
    
    private $isHandling = false;
    
    private $commitBeforeAction = false;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        // Register the change-check listener first to make sure it is called before the 
        // call-action listener even if both listen the OnBeforeUpdateData event
        if ($this->hasRestrictionOnAttributeChange()) {
            $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckChange'], $this->getPriority());
        }
        
        foreach ($this->getEventAliases() as $event) {
            $this->getWorkbench()->eventManager()->addListener($event, [$this, 'onEventCallAction'], $this->getPriority());
        }

        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        foreach ($this->getEventAliases() as $event) {
            $this->getWorkbench()->eventManager()->removeListener($event, [$this, 'onEventCallAction']);
        }

        if ($this->hasRestrictionOnAttributeChange()) {
            $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckChange']);
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('event_aliases', $this->getEventAliases());
        $uxon->setProperty('action', $this->getAction()->exportUxonObject());
        if ($this->getPriority() !== null) {
            $uxon->setProperty('priority', $this->getPriority());
        }
        if ($this->hasRestrictionOnAttributeChange()) {
            $uxon->setProperty('only_if_attributes_change', new UxonObject($this->getOnlyIfAttributesChange()));
        }
        if ($this->hasRestrictionOnAttributeChange()) {
            $uxon->setProperty('only_if_data_matches_conditions', $this->onlyIfDataMatchesConditionGroupUxon);
        }
        return $uxon;
    }

    /**
     * 
     * @return string[]
     */
    protected function getEventAliases() : array
    {
        return $this->eventAliases;
    }

    /**
     * Call the action when any of these events happen
     * 
     * Technically, any type of event selector will do - e.g.: 
     * - `exface.Core.DataSheet.OnBeforeCreateData`
     * - `\exface\Core\Events\DataSheet\OnBeforeCreateData`
     * - OnBeforeCreateData::class (in PHP)
     * 
     * @uxon-property event_aliases
     * @uxon-type metamodel:event[]
     * @uxon-template [""]
     * 
     * @param string $aliasWithNamespace
     * @return CallActionBehavior
     */
    protected function setEventAliases(UxonObject $arrayOfEventAliases) : CallActionBehavior
    {
        $this->eventAliases = $arrayOfEventAliases->toArray();
        return $this;
    }

    /**
     * Call the action when this event happens.
     * 
     * Technically, any type of event selector will do - e.g.: 
     * - `exface.Core.DataSheet.OnBeforeCreateData`
     * - `\exface\Core\Events\DataSheet\OnBeforeCreateData`
     * - OnBeforeCreateData::class (in PHP)
     * 
     * @uxon-property event_alias
     * @uxon-type metamodel:event
     * 
     * @param string $aliasWithNamespace
     * @return CallActionBehavior
     */
    protected function setEventAlias(string $aliasWithNamespace) : CallActionBehavior
    {
        $this->eventAliases = [$aliasWithNamespace];
        return $this;
    }

    /**
     * 
     * @return ActionInterface
     */
    protected function getAction()
    {
        if ($this->action === null) {
            $this->action = ActionFactory::createFromUxon($this->getWorkbench(), UxonObject::fromAnything($this->actionConfig));
            try {
                $this->action->getMetaObject();
            } catch (ActionObjectNotSpecifiedError $e) {
                $this->action->setMetaObject($this->getObject());
            }
        }
        return $this->action;
    }

    /**
     * Sets the action which is executed upon the configured event.
     * 
     * @uxon-property action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * @uxon-required true
     * 
     * @param UxonObject|string $action
     * @return BehaviorInterface
     */
    protected function setAction($action)
    {
        $this->actionConfig = $action;
        return $this;
    }

    /**
     * Executes the action if applicable
     * 
     * @param EventInterface $event
     * @return void
     */
    public function onEventCallAction(EventInterface $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        if ($this->isHandling === true) {
            return;
        }
        
        if (! $event instanceof DataSheetEventInterface) {
            throw new BehaviorConfigurationError($this, 'The CallActionBehavior cannot be triggered by event "' . $event->getAliasWithNamespace() . '": currently only data sheet events supported!');
        }
        
        $eventSheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $eventSheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
		
		$ignoreKey = array_search($eventSheet, $this->ignoreDataSheets, true);
		if ($ignoreKey !== false && null !== $logbook = ($this->ignoreLogbooks[$ignoreKey] ?? null)) {
			$logbook->addSection('Proceeding with event' . $event::getEventName());
		} else {
			$logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
		}
        
        $logbook->addDataSheet('Event data', $eventSheet);
        $logbook->addLine('Found input data for object ' . $eventSheet->getMetaObject()->__toString());
        $logbook->setIndentActive(1);
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        
        if (in_array($eventSheet, $this->ignoreDataSheets, true)) {
            $logbook->addLine('**Skipped** because of `only_if_attributes_change`');
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            return;
        }
        
        $logbook->addLine('Option `event_prevent_default` is `' . $this->getEventPreventDefault() . '`');
        if ($this->getEventPreventDefault() === self::PREVENT_DEFAULT_ALWAYS) {
            $logbook->addLine('Events default logic will be prevented');
            $event->preventDefault();
        }
        
        try {
            // See if relevant
            if ($this->hasRestrictionConditions()) {
                $logbook->addLine('Evaluating `only_if_data_matches_conditions`)');
                $logbook->addLine($this->getOnlyIfDataMatchesConditions()->__toString());
                $inputSheet = $eventSheet->extract($this->getOnlyIfDataMatchesConditions(), true);
                if ($inputSheet->isEmpty()) {
                    $logbook->addLine('**Skipped** because of `only_if_data_matches_conditions`');
                    $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
                    return;
                }
            } else {
                $inputSheet = $eventSheet;
            }
            
            // Now perform the action
            if ($action = $this->getAction()) {
                $logbook->addSection('Running action ' . $action->getAliasWithNamespace());
                $logbook->addIndent(1);

                // Get the task from the event or create one
                if ($event instanceof TaskEventInterface) {
                    $logbook->addLine('Getting task for event and replacing the task data with the input data of the behavior');
                    $task = $event->getTask();
                    $task->setInputData($inputSheet);
                } else {
                    // We never have an input widget here, so tell the action it won't get one
                    // and let it deal with it.
                    $logbook->addLine('Creating a new task because the original event had no task attached');
                    $action->setInputTriggerWidgetRequired(false);
                    $task = TaskFactory::createFromDataSheet($inputSheet);
                }
                
                // Use the tasks transaction if applicable
                if ($event instanceof DataTransactionEventInterface) {
                    $transaction = $event->getTransaction();
                    $logbook->addLine('Getting the transaction from the original event');
                    // Commit the transaction if explicitly requested in the behavior config.
                    // This might be the case if the action calls a external system, which 
                    // relies on the commited data
                    if ($this->willCommitBeforeAction()) {
                        // FIXME wouldn't this prevent further commits???
                       $transaction->commit(); 
                    } else {
                        // Otherwise disable autocommit for the action to force it to use
                        // the same transaction
                        $action->setAutocommit(false);
                    }
                } else {
                    $logbook->addLine('Event has no transaction, so the action will be performed inside a separate transaction');
                    $transaction = null;
                }

                // Handle the task
                $result = $this->callAction($action, $task, $logbook, $transaction);

                // Apply data changes if it is the same object
                if ($result instanceof ResultDataInterface) {
                    $logbook->addLine('Action produced result data ' . DataLogBook::buildTitleForData($result->getData()));
                    if ($result->isDataModified()) {
                        if ($result->getData()->getMetaObject()->isExactly($eventSheet->getMetaObject())) {
                            $logbook->addLine('Updating event data with values from the action because it modified the same object');
                            $eventSheet->merge($result->getData(), true, false);
                        } else {
                            $logbook->addLine('No update of event data required because action returned data of a different object: ' . $result->getData()->getMetaObject()->__toString());
                        }
                    } else {
                        $logbook->addLine('No update of event data required because action did not modify data');
                    }
                } else {
                    $logbook->addLine('Action produced non-data result of type ' . PhpClassDataType::findClassNameWithoutNamespace($result));
                    $logbook->addLine('No update of event data required because action result does not contain data');
                }

                // Prevent event default if needed
                if ($this->getEventPreventDefault() === self::PREVENT_DEFAULT_IF_ACTION_CALLED) {
                    $logbook->addLine('Event default logic will be prevented');
                    $event->preventDefault();
                }
                $logbook->addIndent(-1);
                $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            } else {
                $logbook->addLine('No action to perform');
                $logbook->addIndent(-1);
                $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            }
        } catch (\Throwable $e) {
            if ($this->isErrorIfActionFails()) {
                throw new BehaviorRuntimeError($this, 'Error in ' . $this->getAlias() . ' (' . $this->getName() . '): ' . $e->getMessage(), null, $e, $logbook);
            } 
            $logbook->addLine('**Failed** silently (silenced by `error_if_action_fails`): ' . $e->getMessage());
            $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            $this->getWorkbench()->getLogger()->logException($e);
        }
    }

    protected function callAction(ActionInterface $action, TaskInterface $task, DataLogBookInterface $logbook, DataTransactionInterface $transaction = null) : ResultInterface
    {
        $this->isHandling = true;
        $inputSheet = $task->getInputData();
        $logbook->addLine('**Performing action**' . ($inputSheet !== null ? ' with input data ' . DataLogBook::buildTitleForData($inputSheet) : ''), -1);
        
        if ($this->willBypassDataAuthorizationPoint() === true) {
            $logbook->addLine('BYPASS data authorization because `bypass_data_authorization_point` is `' . ($this->willBypassDataAuthorizationPoint() ?? 'null') . '`');
            $callback = function() use ($action, $task, $transaction) {
                return $action->handle($task, $transaction);
            };
            $result = $this->bypassDataAuthorization($callback);
        } else {
            $logbook->addLine('Enforcing data authorization inside the action regularly because `bypass_data_authorization_point` is `' . ($this->willBypassDataAuthorizationPoint() ?? 'null') . '`');
            $result = $action->handle($task, $transaction);
        }

        $this->isHandling = false;
        return $result;
    }
    
    /**
     * Checks if any of the `only_if_attribtues_change` attributes are about to change
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @return void
     */
    public function onBeforeUpdateCheckChange(OnBeforeUpdateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        if ($this->isHandling === true) {
            return;
        }
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $event->getDataSheet()->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        if (! empty($this->getOnlyIfAttributesChange())) {
			$logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
			$logbook->setIndentActive(1);
			$logbook->addLine('Checking `only_if_attributes_change`: ' . implode(', ', $this->getOnlyIfAttributesChange()));
            $ignore = true;
            foreach ($this->getOnlyIfAttributesChange() as $attrAlias) {
                if ($event->willChangeColumn(DataColumn::sanitizeColumnName($attrAlias))) {
                    $ignore = false;
					$logbook->addLine('Detected change in column "' . $attrAlias . '"', 1);
                    break;
                }
            }
            if ($ignore === true) {
                $this->ignoreDataSheets[] = $event->getDataSheet();
				$this->ignoreLogbooks[] = $logbook;
            }
        }
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getOnlyIfAttributesChange() : array
    {
        return $this->onlyIfAttributesChange ?? [];
    }
    
    /**
     * Only call the action if any of these attributes change (list of aliases)
     * 
     * @uxon-property only_if_attributes_change
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param UxonObject $value
     * @return CallActionBehavior
     */
    protected function setOnlyIfAttributesChange(UxonObject $value) : CallActionBehavior
    {
        $this->onlyIfAttributesChange = $value->toArray();
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasRestrictionOnAttributeChange() : bool
    {
        return $this->onlyIfAttributesChange !== null;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasRestrictionConditions() : bool
    {
        return $this->onlyIfDataMatchesConditionGroupUxon !== null;
    }
    
    /**
     * 
     * @return ConditionGroupInterface|NULL
     */
    protected function getOnlyIfDataMatchesConditions() : ?ConditionGroupInterface
    {
        if ($this->onlyIfDataMatchesConditionGroupUxon === null) {
            return null;
        }
        return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->onlyIfDataMatchesConditionGroupUxon, $this->getObject());
    }
    
    /**
     * Only call the action if it's input data would match these conditions
     * 
     * @uxon-property only_if_data_matches_conditions
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "=","value": ""}]}
     * 
     * @param UxonObject $uxon
     * @return CallActionBehavior
     */
    protected function setOnlyIfDataMatchesConditions(UxonObject $uxon) : CallActionBehavior
    {
        $this->onlyIfDataMatchesConditionGroupUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isErrorIfActionFails() : bool
    {
        return $this->onFailError;
    }
    
    /**
     * Set to FALSE to silence errors if the called action fails
     * 
     * @uxon-property error_if_action_fails
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return CallActionBehavior
     */
    public function setErrorIfActionFails(bool $value) : CallActionBehavior
    {
        $this->onFailError = $value;
        return $this;
    }
    
    /**
     * Set to TRUE to call a commit on the transaction of the event
     *
     * @uxon-property commit_before_action
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $value
     * @return CallActionBehavior
     */
    public function setCommitBeforeAction(bool $value) : CallActionBehavior
    {
        $this->commitBeforeAction = $value;
        return $this;
    }
    
    protected function willCommitBeforeAction() : bool
    {
        return $this->commitBeforeAction;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getEventPreventDefault() : ?string
    {
        return $this->eventPreventDefault;
    }
    
    /**
     * Allows to prevent the default event consequence `always`, `never` or `if_action_called`.
     * 
     * @uxon-property event_prevent_default
     * @uxon-type [always,never,if_action_called]
     * @uxon-default never
     * @uxon-template if_action_called
     * 
     * @param string $value
     * @return CallActionBehavior
     */
    protected function setEventPreventDefault(string $value) : CallActionBehavior
    {
        $this->eventPreventDefault = $value;
        return $this;
    }
}