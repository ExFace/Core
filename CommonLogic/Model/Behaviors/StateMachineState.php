<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Behaviors\StateMachineBehavior;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;

/**
 * Defines a state for the StateMachineBehavior.
 *
 * @author Andrej Kabachnik
 */
class StateMachineState implements iHaveIcon
{
    use TranslatablePropertyTrait;
    
    use iHaveIconTrait;
    
    use ImportUxonObjectTrait;

    private $state_id = null;

    private $buttons = null;

    private $disabled_attributes_aliases = [];
    
    private $disable_editing = false;
    
    private $disable_delete = false;

    /**
     * 
     * @var string[]|NULL
     */
    private $transitions = null;

    private $name = null;

    private $name_translation_key = null;
    
    private $color = null;
    
    private $icon = null;
    
    private $stateMachine = null;
    
    private $notifications = null;
    
    private $description = null;
    
    public function __construct(StateMachineBehavior $stateMachine, $stateId, UxonObject $uxon = null)
    {
        $this->stateMachine = $stateMachine;
        $this->state_id = $stateId;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }

    /**
     * Returns the state id.
     *
     * @return integer|string
     */
    public function getStateId()
    {
        return $this->state_id;
    }

    /**
     * Returns the buttons for the state.
     *
     * @return UxonObject
     */
    public function getButtons()
    {
        if ($this->buttons === null) {
            $this->buttons = [];
            foreach ($this->getTransitions() as $stateId => $actionAlias) {
                if ($actionAlias === '' || $actionAlias === null) {
                    $state = $this->getStateMachine()->getState($stateId);
                    $btnUxon = new UxonObject([
                        "action" => [
                            "alias" => "exface.core.UpdateData",
                            "input_rows_min" => 1,
                            "input_object_alias" => $this->getStateMachine()->getObject()->getAliasWithNamespace(),
                            "input_mappers"=> [
                                [
                                    "from_object_alias" => $this->getStateMachine()->getObject()->getAliasWithNamespace(),
                                    "inherit_columns_only_for_system_attributes" => true,
                                    "column_to_column_mappings" => [
                                        [
                                            "from" => "'$stateId'",
                                            "to" => $this->getStateMachine()->getStateAttributeAlias()
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]);
                    $btnUxon->setProperty('caption', $state->getName());
                    if ($state->getShowIcon() !== false) {
                        $btnUxon->setProperty('icon', $state->getIcon() ?? 'arrow-right');
                        if ($state->getIconSet() !== null) {
                            $btnUxon->setProperty('icon_set', $state->getIconSet());
                        }
                    }
                } else {
                    $btnUxon = new UxonObject([
                        "action_alias" => $actionAlias
                    ]);
                }
                $this->buttons[$stateId] = $btnUxon;
            }
        }
        return $this->buttons;
    }

    /**
     * Defines the transition-buttons for the state.
     * 
     * If not set, but `transitions` defined, buttons for each transition state will
     * be generated automatically using the built-in action `exface.Core.UpdateData`
     * with an `input_mapper` for the state-attribute - see example below
     *
     * Example:
     * 
     * ```
     * {
     *  "states": [
     *      "10": { 
     *          "name": "Created",
     *          "buttons": {
     *              "20": {
     *                  "caption": "Target state name",
     *                  "action": {
     *                      "alias": "exface.core.UpdateData",
     *                      "input_rows_min": 1,
     *                      "input_object_alias":"my.App.object_of_behavior",
     *                      "input_mappers":[{
     *                              "from_object_alias": "exface.Core.MONITOR_ERROR",
     *                              "inherit_columns_only_for_system_attributes": true,
     *                              "column_to_column_mappings":[
     *                                  {"from": 20,"to":"STATUS"}
     *                               ]
     *                      }]
     *                  }
     *              }
     *          }
     *      }
     *  }
     *  
     * ```
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-template {"10": {"caption": "", "action": {"alias": ""}}, "20": {"caption": "", "action": {"alias": ""}}}
     *
     * @param UxonObject $value            
     * @return \exface\Core\CommonLogic\Model\Behaviors\StateMachineState
     */
    protected function setButtons($value)
    {
        $this->buttons = $value;
        return $this;
    }

    /**
     * Defines the disabled attributes aliases for the state.
     *
     * Example:
     * 
     * ```
     * {
     *  "states": [
     *      "20": {
     *          "name": "Confirmed",
     *          "disabled_attribute_aliases": [
     *              "DOCUMENT_NO",
     *              "DOCUMENT_DATE"
     *          ]
     *      }
     *  ]
     * }
     *  
     * ```
     * 
     * @uxon-property disabled_attributes_aliases
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param UxonObject|string[] $value            
     * @return \exface\Core\CommonLogic\Model\Behaviors\StateMachineState
     */
    protected function setDisabledAttributesAliases($value)
    {
        if ($value instanceof UxonObject){
            $array = $value->toArray();
        } elseif (is_array($value)){
            $array = $value;
        } else {
            throw new UnexpectedValueException('Invalid format for disabled attribute aliases for StateMachineBehavior! Array expected!');
        }
        $this->disabled_attributes_aliases = $array;
        return $this;
    }

    /**
     * Returns the disabled attributes aliases for the state.
     *
     * @return string[]
     */
    public function getDisabledAttributesAliases()
    {
        return $this->disabled_attributes_aliases;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasDisabledAttributes() : bool
    {
        return ! empty($this->disabled_attributes_aliases);
    }
    
    /**
     * Returns TRUE if transitions were defined for this state and FALSE otherwise.
     * 
     * @return bool
     */
    public function hasTransitionRestrictions() : bool
    {
        return $this->transitions !== null;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getTransitions(bool $autoGenerate = true) : array
    {
        $array = $this->transitions;
        if ($array === null) {
            if ($autoGenerate === true) {
                foreach($this->getStateMachine()->getStateIds() as $id) {
                    $array[$id] = '';
                }
            } else {
                $array = [];
            }
        } else {
            if ($this->getDisableEditing() !== true && ! array_key_exists($this->getStateId(), $array)) {
                $array[$this->getStateId()] = '';
            }
        }
        
        return $array;
    }

    /**
     * Defines the allowed transitions for the state (if not set, transitions to all states are allowed).
     * 
     * If set, it will only be possible to change the state of the object to the listed states!
     * The transition validation is done automatically with every DataSheet operation, so it is
     * a pretty solid restriction.
     * 
     * Use an empty object to forbid any transitions from a state (including to itself!). Set to
     * an list containing only the key of this state to forbid any transitions, but still allow
     * changing data in this state.
     * 
     * Additionally an action can be specified for each transition. In contrast to the state
     * transition itself, this is not a restriction, but rather a helpful hint for the metamodel
     * logic and also for human model designers. If an action is defined, auto-generated state 
     * buttons will trigger that action instead of a generic state value update. However, this
     * does not mean, the transition to the given state can only be done via this action: it
     * can still happen by explicit state value update or by another action and so on.
     * 
     * Also make sure, transition actions always have `input_invalid_if` conditions to make sure
     * they are applied in the correct state - this validation is not done by the behavior 
     * automatically!
     *
     * The below example illustrates a state machine with the following rules:
     * 
     * - A drafted document (state 10) needs to be approved before being submitted. 
     * - An approved document (state 50) can be submitted or modified, but cannot become a draft again
     * - A submitted document (state 99) cannot be changed at all - even without changing the state!
     * 
     * ```
     *  {
     *      "10": {
     *          "name": "Draft",
     *          "transitions": {
     *              "10": "",
     *              "50": "my.App.Approve"
     *          }
     *      },
     *      "50": {
     *          "Approved",
     *          "transitions": {
     *              "50": "",
     *              "99": "my.App.Submit"
     *          }
     *      },
     *      "99": {
     *          "name": "Submitted",
     *          "transitions": {}
     *      }
     *  }
     *  
     * ``` 
     *  
     * @uxon-property transitions
     * @uxon-type metamodel:action[]
     * @uxon-template {"10": "", "": ""}
     *
     * @param UxonObject $value            
     * @return \exface\Core\CommonLogic\Model\Behaviors\StateMachineState
     */
    protected function setTransitions(UxonObject $value)
    {
        $array = [];
        
        // Legacy syntax where transitions were merely an array with state ids
        if ($value->isArray()) {
            foreach ($value as $stateId) {
                $array[$stateId] = '';
            }
        } 
        // New syntax where each transition may be an action
        else {
            $array = $value->toArray();
        }
        $this->transitions = $array;
        return $this;
    }

    /**
     * Defines the name for the state (use =TRANSLATE() for translatable names).
     * 
     * @uxon-property name
     * @uxon-type string|metamodel:formula
     *
     * @param string $name            
     */
    protected function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the name of the state.
     * 
     * If the name was specified as a formula (e.g. =TRANSLATE(...)), the formula will be evaluated.
     *
     * @return string
     */
    public function getName($prependId = false)
    {
        if ($this->name !== null && $this->isValueFormula($this->name) === true) {
            try {
                $this->name = $this->evaluatePropertyExpression($this->name);
            } catch (RuntimeException $e) {
                throw new BehaviorConfigurationError($this->getStateMachine()->getObject(), 'Invalid value for state name "' . $this->name . '": only strings and static formulas like =TRANSLATE() are allowed!', $e->getAlias(), $e);
            }
        }
        return ($prependId === true ? $this->getStateId() . ' ' : '') . $this->name;
    }
    
    /**
     * Sets a custom color for this state (used in all sorts of indicators like progress bars).
     * 
     * You can use hexadecimal color codes or HTML color names.
     * 
     * @uxon-property color
     * @uxon-type color
     * 
     * @param string $color_name_or_code
     * @return StateMachineState
     */
    protected function setColor($color_name_or_code)
    {
        $this->color = $color_name_or_code;
        return $this;
    }
    
    /**
     * 
     * @return string|null
     */
    public function getColor() : ?string
    {
        return $this->color;
    }

    /**
     * 
     * @return bool
     */
    public function getDisableEditing() : bool
    {
        return $this->disable_editing;
    }

    /**
     * Prevents instances of the object from being edited/modified in this state if set to TRUE.
     * 
     * This is a shortcut to putting all editable attributes into disabled_attribute_aliases.
     * 
     * @uxon-property disable_editing
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return StateMachineState
     */
    protected function setDisableEditing(bool $trueOrFalse) : StateMachineState
    {
        $this->disable_editing = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getDisableDelete() : bool
    {
        return $this->disable_delete;
    }

    /**
     * Prevents instances of the object in the current state from being deleted if set to TRUE.
     * 
     * @uxon-property disable_delete
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param int|string|bool $trueOrFalse
     * @return StateMachineState
     */
    protected function setDisableDelete(bool $trueOrFalse) : StateMachineState
    {
        $this->disable_delete = $trueOrFalse;
        return $this;
    }

    /**
     * Returns TRUE if a transition from this state to the given one is possible.
     * 
     * @param StateMachineState $toState
     * @return bool
     */
    public function isTransitionAllowed(StateMachineState $toState) : bool
    {
        return $this->hasTransitionRestrictions() === false || array_key_exists($toState->getStateId(), $this->getTransitions()) === true;
    }
    
    /**
     * Returns TRUE if the editing of the given attribute is not blocked by this state and FALSE otherwise.
     * 
     * @param MetaAttributeInterface $attribute
     * @return bool
     */
    public function isAttributeDisabled(MetaAttributeInterface $attribute) : bool
    {
        return $this->getDisableEditing() === true || in_array($attribute->getAliasWithRelationPath(), $this->getDisabledAttributesAliases()) === true;
    }

    /**
     * Returns TRUE if the given action is forbidden in this state.
     * 
     * This is the case if the action modifies or deletes data and the state has `disable_editing` = true.
     * 
     * @param ActionInterface $action
     * @return bool
     */
    public function isActionDisabled(ActionInterface $action) : bool
    {
        return $this->getDisableEditing() === true && ($action instanceof iModifyData) && ! ($action instanceof iCreateData);
    }
    
    /**
     * 
     * @return StateMachineBehavior
     */
    public function getStateMachine() : StateMachineBehavior
    {
        return $this->stateMachine;
    }
    
    /**
     * Array of messages to send when this state is reached - each with their own channel, recipients, etc.
     *
     * You can use the following placeholders inside any message model - as recipient,
     * message subject - anywhere:
     *
     * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
     * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key`
     * from the given app
     * - `[#~data:column_name#]` - will be replaced by the value from `column_name` of the data sheet,
     * for which the notification was triggered - only works with notification on data sheet events!
     * - `[#=Formula()#]` - will evaluate the `Formula` (e.g. `=Now()`) in the context of the notification.
     * This means, static formulas will always work, while data-driven formulas will only work on data sheet
     * events!
     *
     * @uxon-property notifications
     * @uxon-type \exface\Core\CommonLogic\Communication\AbstractMessage
     * @uxon-template [{"channel": ""}]
     * 
     * @param UxonObject $uxonArray
     * @return StateMachineState
     */
    protected function setNotifications(UxonObject $uxonArray) : StateMachineState
    {
        $this->notifications = $uxonArray;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasNotifications() : bool
    {
        return $this->notifications !== null;
    }
    
    /**
     * 
     * @return UxonObject|NULL
     */
    public function getNotificationsUxon() : ?UxonObject
    {
        return $this->notifications;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getDescription() : ?string
    {
        return $this->description;
    }
    
    /**
     * Detailed description of the state - used in generated documentation and simply helps read the state machine config
     * 
     * Use this property to describe who does what in each particular state and what is the expected
     * outcome. Focus on business, not technical details.  This helps understand the configuration once 
     * it gets complexer with lots of technical stuff like transitions, notifications, etc.
     * 
     * @uxon-property description
     * @uxon-type string
     * 
     * @param string $text
     * @return StateMachineBehavior
     */
    public function setDescription(string $text) : StateMachineState
    {
        $this->description = $text;
        return $this;
    }
}