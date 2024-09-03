<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
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

/**
 * Attachable to DataSheetEvents (exface.Core.DataSheet.*), calls any action.
 * 
 * For this behavior to work, it has to be attached to an object in the metamodel. The event-
 * alias and the action have to be configured in the behavior configuration.
 * 
 * ## Examples
 * 
 * ### Call an ection every time an instance of this object is created
 * 
 * ```
 * {
 *  "event_alias": "exface.Core.DataSheet.OnBeforeCreateData",
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
    
    private $eventAlias = null;
    
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
        
        $this->getWorkbench()->eventManager()->addListener($this->getEventAlias(), [$this, 'onEventCallAction'], $this->getPriority());
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener($this->getEventAlias(), [$this, 'onEventCallAction'], $this->getPriority());
        
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
        $uxon->setProperty('event_alias', $this->getEventAlias());
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
     * @return string
     */
    protected function getEventAlias() : string
    {
        return $this->eventAlias;
    }

    /**
     * Alias of the event, that should trigger the action.
     * 
     * Technically, any type of event selector will do - e.g.: 
     * - `exface.Core.DataSheet.OnBeforeCreateData`
     * - `\exface\Core\Events\DataSheet\OnBeforeCreateData`
     * - OnBeforeCreateData::class (in PHP)
     * 
     * @uxon-property event_alias
     * @uxon-type metamodel:event
     * @uxon-required true
     * 
     * @param string $aliasWithNamespace
     * @return CallActionBehavior
     */
    protected function setEventAlias(string $aliasWithNamespace) : CallActionBehavior
    {
        $this->eventAlias = $aliasWithNamespace;
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
        
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
		
		$ignoreKey = array_search($data_sheet, $this->ignoreDataSheets, true);
		if ($ignoreKey !== false && null !== $logbook = ($this->ignoreLogbooks[$ignoreKey] ?? null)) {
			$logbook->addSection('Proceeding with event' . $event::getEventName());
		} else {
			$logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
		}
        
        $logbook->addDataSheet('Input data', $data_sheet);
        $logbook->addLine('Found input data for object ' . $data_sheet->getMetaObject()->__toString());
        $logbook->setIndentActive(1);
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event, $logbook));
        
        if (in_array($data_sheet, $this->ignoreDataSheets, true)) {
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
                $data_sheet = $data_sheet->extract($this->getOnlyIfDataMatchesConditions(), true);
                if ($data_sheet->isEmpty()) {
                    $logbook->addLine('**Skipped** because of `only_if_data_matches_conditions`');
                    $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
                    return;
                }
            }
            
            // Now handle the action
            if ($action = $this->getAction()) {
                $logbook->addSection('Running action ' . $action->getAliasWithNamespace());
                if ($event instanceof TaskEventInterface) {
                    $logbook->addLine('Getting task for event and replacing the task data with the input data of the behavior');
                    $task = $event->getTask();
                    $task->setInputData($data_sheet);
                } else {
                    // We never have an input widget here, so tell the action it won't get one
                    // and let it deal with it.
                    $logbook->addLine('Creating a new task because the event has no task attached');
                    $action->setInputTriggerWidgetRequired(false);
                    $task = TaskFactory::createFromDataSheet($data_sheet);
                }
                
                if ($event instanceof DataTransactionEventInterface) {
                    $logbook->addLine('Getting the transaction from the event');
                    $this->isHandling = true;
                    // commit the transaction in case the action calls a external
                    // system which relies on the commited data
                    if ($this->getCommitBeforeAction()) {
                       $event->getTransaction()->commit(); 
                    }
                    $action->handle($task, $event->getTransaction());
                    $this->isHandling = false;
                } else {
                    $logbook->addLine('Event has no transaction, so the action will be performed inside a separate transaction');
                    $logbook->addLine('**Performing action**');
                    $this->isHandling = true;
                    $action->handle($task);
                    $this->isHandling = false;
                }
                if ($this->getEventPreventDefault() === self::PREVENT_DEFAULT_IF_ACTION_CALLED) {
                    $logbook->addLine('Events default logic will be prevented');
                    $event->preventDefault();
                }
                $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event, $logbook));
            } else {
                $logbook->addLine('No action to perform');
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
    
    protected function getCommitBeforeAction() : bool
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